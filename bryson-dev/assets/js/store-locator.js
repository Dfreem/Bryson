(function ($) {

    /* -----------------------------
       1. US + Canada Code Mappings
    ------------------------------*/
    const stateNameToCode = {
        // USA
        "Alabama": "AL", "Alaska": "AK", "Arizona": "AZ", "Arkansas": "AR", "California": "CA",
        "Colorado": "CO", "Connecticut": "CT", "Delaware": "DE", "Florida": "FL", "Georgia": "GA",
        "Hawaii": "HI", "Idaho": "ID", "Illinois": "IL", "Indiana": "IN", "Iowa": "IA", "Kansas": "KS",
        "Kentucky": "KY", "Louisiana": "LA", "Maine": "ME", "Maryland": "MD", "Massachusetts": "MA",
        "Michigan": "MI", "Minnesota": "MN", "Mississippi": "MS", "Missouri": "MO", "Montana": "MT",
        "Nebraska": "NE", "Nevada": "NV", "New Hampshire": "NH", "New Jersey": "NJ", "New Mexico": "NM",
        "New York": "NY", "North Carolina": "NC", "North Dakota": "ND", "Ohio": "OH", "Oklahoma": "OK",
        "Oregon": "OR", "Pennsylvania": "PA", "Rhode Island": "RI", "South Carolina": "SC",
        "South Dakota": "SD", "Tennessee": "TN", "Texas": "TX", "Utah": "UT", "Vermont": "VT",
        "Virginia": "VA", "Washington": "WA", "West Virginia": "WV", "Wisconsin": "WI", "Wyoming": "WY",

        // CANADA
        "Alberta": "AB", "British Columbia": "BC", "Manitoba": "MB", "New Brunswick": "NB",
        "Newfoundland and Labrador": "NL", "Northwest Territories": "NT", "Nova Scotia": "NS",
        "Nunavut": "NU", "Ontario": "ON", "Prince Edward Island": "PE", "Quebec": "QC",
        "Saskatchewan": "SK", "Yukon": "YT"
    };

    /* -----------------------------
       2. Build Tab UI
    ------------------------------*/
    // Widen the content column on this page only
    const primaryEl = document.getElementById('primary');
    if (primaryEl) {
        primaryEl.style.maxWidth = '100%';
        primaryEl.style.width = '100%';
    }

    const mapEl = document.getElementById('store-map');
    mapEl.innerHTML = `
        <div id="map-tab-bar" class="nav nav-tabs">
            <div class="nav-item" style="cursor: pointer;">
                <a class="nav-link active map-tab" data-tab="us">United States</a>
            </div>
            <div class="nav-item" style="cursor: pointer;">
                <a class="nav-link map-tab" data-tab="ca">Canada</a>
            </div>
        </div>
        <div id="map-panel-us"></div>
        <div id="map-panel-ca" style="display:none;"></div>
    `;

    document.querySelectorAll('.map-tab').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.map-tab').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            const tab = this.dataset.tab;
            document.getElementById('map-panel-us').style.display = tab === 'us' ? '' : 'none';
            document.getElementById('map-panel-ca').style.display = tab === 'ca' ? '' : 'none';

            // Refit Canada viewBox now that the panel is visible
            if (tab === 'ca') {
                requestAnimationFrame(() => {
                    const bb = gCA.node().getBBox();
                    if (bb.width > 0) {
                        svgCA.attr('viewBox', `${bb.x} ${bb.y} ${bb.width} ${bb.height}`);
                    }
                });
            }
        });
    });

    /* -----------------------------
       3. US SVG
    ------------------------------*/
    const svgUS = d3.select('#map-panel-us').append('svg')
        .attr('viewBox', '0 0 960 600')
        .attr('width', '100%');

    const gUS = svgUS.append('g');

    /* -----------------------------
       4. Canada SVG
    ------------------------------*/
    const svgCA = d3.select('#map-panel-ca').append('svg')
        .attr('width', '100%');

    const gCA = svgCA.append('g');

    /* -----------------------------
       5. US Projection + Load
    ------------------------------*/
    const projectionUS = d3.geoAlbersUsa().scale(1280).translate([480, 300]);
    const pathUS = d3.geoPath(projectionUS);

    d3.json('https://cdn.jsdelivr.net/npm/us-atlas@3/states-10m.json').then(us => {
        const features = topojson.feature(us, us.objects.states).features;

        gUS.selectAll('path')
            .data(features)
            .join('path')
            .attr('d', pathUS)
            .attr('fill', '#378ADD')
            .attr('stroke', '#fff')
            .attr('stroke-width', 0.5)
            .style('cursor', 'pointer')
            .on('mouseover', function () {
                d3.select(this).attr('fill', '#185FA5');
            })
            .on('mouseout', function () {
                if (!d3.select(this).classed('active')) {
                    d3.select(this).attr('fill', '#378ADD');
                }
            })
            .on('click', function (event, d) {
                handleRegionClick(d.properties.name, "US", this);
            });
    });

    /* -----------------------------
       6. Canada Load
    ------------------------------*/
    import('https://esm.sh/@svg-maps/canada').then(({ default: Canada }) => {
        gCA.selectAll('path')
            .data(Canada.locations)
            .join('path')
            .attr('d', d => d.path)
            .attr('fill', '#6BB5FF')
            .attr('stroke', '#fff')
            .attr('stroke-width', 0.5)
            .style('cursor', 'pointer')
            .on('mouseover', function () {
                d3.select(this).attr('fill', '#3A8FD8');
            })
            .on('mouseout', function () {
                if (!d3.select(this).classed('active')) {
                    d3.select(this).attr('fill', '#6BB5FF');
                }
            })
            .on('click', function (event, d) {
                handleRegionClick(d.name, "CA", this);
            });

        // Set viewBox from the library's own metadata
        if (Canada.viewBox) {
            svgCA.attr('viewBox', Canada.viewBox);
        }
    });

    /* -----------------------------
       7. Shared Click Handler
    ------------------------------*/
    function handleRegionClick(name, country, element) {
        const code = stateNameToCode[name];
        if (!code) return;
        gUS.selectAll('path').classed('active', false).attr('fill', '#378ADD');
        gCA.selectAll('path').classed('active', false).attr('fill', '#6BB5FF');

        d3.select(element)
            .classed('active', true)
            .attr('fill', country === "US" ? "#0C447C" : "#004C99");

        const header = document.getElementById('store-state-header');
        const grid = document.getElementById('store-grid');

        header.style.display = 'block';
        header.textContent = `Loading ${name}...`;
        grid.innerHTML = '';

        $.post(storeLocator.ajaxurl, {
            action: 'get_stores',
            nonce: storeLocator.nonce,
            state: code,
            country: country
        }, function (response) {
            if (!response.success || !response.data.length) {
                header.textContent = `${name} — no stores on file`;
                return;
            }

            const stores = response.data;
            header.textContent = `${name} — ${stores.length} store${stores.length !== 1 ? 's' : ''}`;

            grid.innerHTML = stores.map(s => `
                <div style="background:#fff;border:0.5px solid #ddd;border-radius:8px;padding:12px 14px;">
                    <p style="font-size:14px;font-weight:500;margin:0 0 4px;">${s.name}</p>
                    <p style="font-size:12px;color:#666;margin:0 0 2px;">${s.address}, ${s.city} ${s.zip}</p>
                    ${s.phone ? `<p style="font-size:12px;color:#666;margin:0 0 2px;">${s.phone}</p>` : ''}
                    ${s.website ? `<a style="font-size:12px;color:#378ADD;" href="https://${s.website}" target="_blank">${s.website}</a>` : ''}
                </div>
            `).join('');

            header.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        });
    }

})(jQuery);
