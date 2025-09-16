(function($) {
    var debounceTimer;
    var xhr;
    var $modal, $backdrop, $input, $resultsContainer, $closeBtn;
    var scrollPosition = 0;

    function openModal() {
        scrollPosition = $(window).scrollTop();
        $('html').addClass('asmi-modal-open');
        $('body').addClass('asmi-modal-open').css('top', -scrollPosition + 'px');
        
        $backdrop.addClass('is-active');
        $modal.addClass('is-active');
        
        setTimeout(function() {
            $input.focus();
        }, 100);
    }

    function closeModal() {
        $('html').removeClass('asmi-modal-open');
        $('body').removeClass('asmi-modal-open').css('top', '');
        $(window).scrollTop(scrollPosition);

        $backdrop.removeClass('is-active');
        $modal.removeClass('is-active');
    }

    function init() {
        $modal = $('#asmi-search-modal');
        $backdrop = $('#asmi-search-modal-backdrop');
        $input = $('#asmi-modal-q');
        $resultsContainer = $('#asmi-modal-results');
        $closeBtn = $('#asmi-modal-close');

        if ($('html').hasClass('asmi-modal-open')) {
            closeModal();
        }

        $(document).on('click', '#asmi-modal-trigger-icon, .asmi-modal-trigger', openModal);

        $closeBtn.on('click', closeModal);
        $backdrop.on('click', closeModal);
        $(document).on('keydown', function(e) {
            if (e.key === "Escape" && $modal.hasClass('is-active')) {
                closeModal();
            }
        });

        $input.on('keyup', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(performSearch, 300);
        });

        $resultsContainer.on('click', '.asmi-results-tab-btn', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var paneId = $btn.data('pane');
            $btn.addClass('active').siblings().removeClass('active');
            $resultsContainer.find('.asmi-results-pane').removeClass('active');
            $('#' + paneId).addClass('active');
        });
    }

    function performSearch() {
        var term = $input.val().trim();

        if (xhr) {
            xhr.abort();
        }

        if (term.length < 2) {
            $resultsContainer.empty().hide();
            return;
        }
        
        $resultsContainer.html('<div class="asmi-spinner"></div>').show();

        xhr = $.ajax({
            url: ASMI.endpoint,
            data: { 
                q: term,
                lang: ASMI.lang
            },
            success: function(response) {
                renderResults(response, term);
            },
            error: function(jqXHR) {
                if (jqXHR.statusText !== 'abort') {
                    $resultsContainer.html('<div class="asmi-info-message">Ein Fehler ist aufgetreten.</div>');
                }
            }
        });
    }

    // KORRIGIERTE FUNKTION
    function renderItem(item) {
        var titleAttr = (item.title || '').replace(/"/g, '&quot;');
        var img = item.image ? '<img src="' + item.image + '" alt="' + titleAttr + '" loading="lazy"/>' : '<span class="asmi-result-no-image"></span>';
        
        var finalUrl = item.url;
        if (item.source === 'product' && ASMI.utmParameters) {
            var connector = finalUrl.indexOf('?') === -1 ? '?' : '&';
            finalUrl += connector + ASMI.utmParameters;
        }

        // NEU: Logik für die Anzeige von Produktdetails
        var detailsHtml = '';
        if (item.source === 'product') {
            detailsHtml += '<div class="asmi-product-details">';
            if (item.sku) {
                detailsHtml += '<span><strong>MPN/SKU:</strong> ' + item.sku + '</span>';
            }
            if (item.gtin) {
                detailsHtml += '<span><strong>EAN/GTIN:</strong> ' + item.gtin + '</span>';
            }
            detailsHtml += '</div>';
        }

        return `
            <a href="${finalUrl}" class="asmi-result-link" target="_blank" rel="noopener">
                <article>
                    <div class="asmi-result-image">${img}</div>
                    <div class="asmi-result-content">
                        <h4 class="asmi-result-title">${item.title || ''}</h4>
                        ${detailsHtml} 
                    </div>
                </article>
            </a>
        `;
    }

    function renderResults(data, term) {
        var products = data.results.products || [];
        var wordpress = data.results.wordpress || [];

        if (products.length === 0 && wordpress.length === 0) {
            $resultsContainer.html('<div class="asmi-info-message">' + ASMI.labels.no_results + '</div>');
            return;
        }

        var tabsNav = '';
        var tabsContent = '';
        var hasProducts = products.length > 0;
        var hasWordpress = wordpress.length > 0;
        var firstActivePaneId = '';
        var encodedTerm = encodeURIComponent(term);

        if (hasWordpress) {
            firstActivePaneId = 'asmi-pane-info';
            var wpLink = ASMI.wp_search_url.replace('%s', encodedTerm);
            var wpItemsHtml = wordpress.map(renderItem).join('');
            var wpViewAll = '<a href="' + wpLink + '" class="asmi-view-all-link" target="_blank" rel="noopener">' + ASMI.labels.view_all_wp + '</a>';
            tabsNav += '<button class="asmi-results-tab-btn" data-pane="asmi-pane-info">Informationen (' + wordpress.length + ')</button>';
            tabsContent += '<div id="asmi-pane-info" class="asmi-results-pane"><div class="asmi-scrollable-content">' + wpItemsHtml + '</div>' + wpViewAll + '</div>';
        }
        if (hasProducts) {
            if (!firstActivePaneId) { firstActivePaneId = 'asmi-pane-prod'; }
            var productLink = ASMI.product_search_url ? ASMI.product_search_url + encodedTerm : '#';
            var productItemsHtml = products.map(renderItem).join('');
            var productViewAll = ASMI.product_search_url ? '<a href="' + productLink + '" class="asmi-view-all-link" target="_blank" rel="noopener">' + ASMI.labels.view_all_products + '</a>' : '';
            tabsNav += '<button class="asmi-results-tab-btn" data-pane="asmi-pane-prod">Produkte (' + products.length + ')</button>';
            tabsContent += '<div id="asmi-pane-prod" class="asmi-results-pane"><div class="asmi-scrollable-content">' + productItemsHtml + '</div>' + productViewAll + '</div>';
        }

        var finalHtml = '';
        if (hasProducts && hasWordpress) {
            finalHtml = '<div class="asmi-results-tabs-nav">' + tabsNav + '</div><div class="asmi-results-panes-wrapper">' + tabsContent + '</div>';
        } else {
            finalHtml = '<div class="asmi-results-panes-wrapper">' + tabsContent + '</div>';
        }
        
        $resultsContainer.html(finalHtml);
        
        if (firstActivePaneId) {
            $resultsContainer.find('.asmi-results-tab-btn[data-pane="' + firstActivePaneId + '"]').addClass('active');
            $('#' + firstActivePaneId).addClass('active');
        }
    }

    $(document).ready(init);

})(jQuery);