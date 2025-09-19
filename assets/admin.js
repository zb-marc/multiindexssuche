jQuery(function($){
  var asmiAdmin = {
    nonces: {},
    pollingInterval: null,
    wpPollingInterval: null,
    confirmCallback: null,

    init: function() {
      this.nonces = $('.asmi-wrap').data('nonces') || {};
      this.initTabs();
      this.initActions();
      this.initModal();
      this.initDataTabs();
      this.initMediaUploader();
      
      if ($('.nav-tab-wrapper .nav-tab-active[href="#tab-index"]').length || $('.nav-tab-wrapper .nav-tab-active[href="#tab-system"]').length) {
        this.updateStatus();
        this.updateWpStatus();
        this.startPolling();
        this.startWpPolling();
      } else {
         var hash = window.location.hash;
         if (hash.startsWith('#tab-')) {
            $('.nav-tab-wrapper a[href="' + hash + '"]').click();
         }
      }
    },

    initMediaUploader: function() {
        $(document).on('click', '.asmi-upload-btn', function(e) {
            e.preventDefault();
            var $button = $(this);
            var targetInput = $button.data('target-input');
            
            var frame = wp.media({
                title: 'Bild auswählen',
                button: {
                    text: 'Bild verwenden'
                },
                multiple: false
            });

            frame.on('select', function() {
                var attachment = frame.state().get('selection').first().toJSON();
                $(targetInput).val(attachment.url);
            });

            frame.open();
        });
    },

    initTabs: function() {
      var self = this;
      $('.nav-tab-wrapper .nav-tab').on('click', function(e){
        e.preventDefault();
        var target = $(this).attr('href');
        
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        $('.asmi-tab').hide();
        $(target).show();
        
        $('#asmi_active_tab').val(target);

        window.location.hash = target.substring(1);

        // KORREKTUR: Zeige/Verstecke die Export/Import-Formulare beim System-Tab
        if (target === '#tab-system') {
          $('#asmi-system-extra-forms').show();
          self.updateStatus();
          self.startPolling();
        } else {
          $('#asmi-system-extra-forms').hide();
          if (target === '#tab-index') {
            self.updateStatus();
            self.updateWpStatus();
            self.startPolling();
            self.startWpPolling();
          } else {
            self.stopPolling();
            self.stopWpPolling();
          }
        }
      });

      var hash = window.location.hash;
      if (hash && $(hash).length) {
        $('.nav-tab-wrapper a[href="' + hash + '"]').click();
      } else {
        $('.nav-tab-wrapper a[href="#tab-general"]').click();
      }
    },

    initDataTabs: function() {
        $('#tab-data').on('click', '.asmi-data-filter', function(e) {
            e.preventDefault();
            var $btn = $(this);
            $btn.siblings('.asmi-data-filter').removeClass('active');
            $btn.addClass('active');
            var lang = $('.asmi-data-lang-btn.active').data('lang');
            var type = $('.asmi-data-type-btn.active').data('type');
            $('.asmi-data-container').hide();
            $('#asmi-data-container-' + lang + '-' + type).show();
            return false;
        });
    },
    
    initActions: function() {
      var self = this;
      $(document).on('click', 'button[data-action]', function(e) {
          e.preventDefault();
          var $btn = $(this);
          var msg = $btn.data('confirm-msg');
          var action = $btn.data('action');

          var performAction = function() {
              var endpoints = {
                  'reindex': { url: '/wp-json/asmi/v1/index/reindex', msg: 'Feed-Import gestartet...' },
                  'reindex_wp': { url: '/wp-json/asmi/v1/wp-index/start', msg: 'WordPress-Indexierung gestartet...' },
                  'cancel': { url: '/wp-json/asmi/v1/index/cancel', msg: 'Feed-Prozess wird abgebrochen...' },
                  'cancel_wp': { url: '/wp-json/asmi/v1/wp-index/cancel', msg: 'WordPress-Indexierung wird abgebrochen...' },
                  'clear': { url: '/wp-json/asmi/v1/index/clear', msg: 'Index wird geleert...' },
                  'delete_images': { url: '/wp-json/asmi/v1/images/delete/start', msg: 'Löschen der Bilder gestartet...' },
                  'db_repair': { url: '/wp-json/asmi/v1/db/repair', msg: 'Datenbank-Reparatur abgeschlossen.' }
              };
              var endpoint = endpoints[action];
              if (endpoint) {
                self.performAjaxAction(endpoint.url, 'POST', self.nonces[action] || self.nonces.reindex, endpoint.msg, action);
              }
          };

          if (msg) {
            self.showModal(msg, performAction);
          } else {
             performAction();
          }
      });
    },

    performAjaxAction: function(url, method, nonce, successMessage, action) {
        var self = this;
        $.ajax({
            url: url,
            method: method,
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', nonce);
            },
            success: function(response) {
                self.showNotice(successMessage, 'success');
                self.updateStatus();
                self.updateWpStatus();
                self.startPolling();
                self.startWpPolling();
            },
            error: function(jqXHR) {
                var errorMsg = 'Ein Fehler ist aufgetreten.';
                if (jqXHR.responseJSON && jqXHR.responseJSON.message) {
                    errorMsg = jqXHR.responseJSON.message;
                }
                self.showNotice(errorMsg, 'error');
            }
        });
    },
    
    showNotice: function(message, type) {
        var $notice = $('#asmi-admin-notice');
        if (!$notice.length) {
            $('.asmi-wrap h1').after('<div id="asmi-admin-notice" class="notice is-dismissible" style="display:none;"><p></p></div>');
            $notice = $('#asmi-admin-notice');
        }
        $notice.removeClass('notice-success notice-error notice-warning').addClass('notice-' + type);
        $notice.find('p').html(message);
        $notice.fadeIn().delay(4000).fadeOut();
    },

    showModal: function(text, callback) {
        $('#asmi-modal-text').text(text);
        $('#asmi-modal-backdrop, #asmi-modal-wrap').fadeIn(200);
        this.confirmCallback = callback;
    },

    initModal: function() {
        var self = this;
        $('#asmi-modal-confirm').on('click', function() {
            if (typeof self.confirmCallback === 'function') {
                self.confirmCallback();
            }
            self.closeModal();
        });
        $('#asmi-modal-cancel, #asmi-modal-close, #asmi-modal-backdrop').on('click', function() {
            self.closeModal();
        });
    },

    closeModal: function() {
        $('#asmi-modal-backdrop, #asmi-modal-wrap').fadeOut(200);
        this.confirmCallback = null;
    },

    startPolling: function() {
      if(this.pollingInterval) return;
      var self = this;
      this.pollingInterval = setInterval(function() { self.updateStatus(); }, 5000);
    },

    stopPolling: function() {
      clearInterval(this.pollingInterval);
      this.pollingInterval = null;
    },

    startWpPolling: function() {
      if(this.wpPollingInterval) return;
      var self = this;
      this.wpPollingInterval = setInterval(function() { self.updateWpStatus(); }, 5000);
    },

    stopWpPolling: function() {
      clearInterval(this.wpPollingInterval);
      this.wpPollingInterval = null;
    },
    
    updateStatus: function() {
        var self = this;
        $.when(
            $.ajax({ url: '/wp-json/asmi/v1/index/status', method: 'GET', beforeSend: function(xhr){ xhr.setRequestHeader('X-WP-Nonce', self.nonces.status); }}),
            $.ajax({ url: '/wp-json/asmi/v1/images/delete/status', method: 'GET', beforeSend: function(xhr){ xhr.setRequestHeader('X-WP-Nonce', self.nonces.delete_status); }})
        ).done(function(indexResp, imageDeleteResp){
            self.updateDashboard(indexResp[0], imageDeleteResp ? imageDeleteResp[0] : {});
        });
    },

    updateWpStatus: function() {
        var self = this;
        $.ajax({
            url: '/wp-json/asmi/v1/wp-index/status',
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', self.nonces.status);
            },
            success: function(response) {
                self.updateWpDashboard(response.state || {});
            }
        });
    },

    formatDate: function(ts) {
        if (!ts || ts < 1) return '–';
        var d = new Date(ts * 1000);
        return d.toLocaleString();
    },

    formatDuration: function(seconds) {
        if (seconds < 0 || isNaN(seconds)) return '0s';
        if (seconds < 60) return Math.round(seconds) + 's';
        var m = Math.floor(seconds / 60);
        var s = Math.round(seconds % 60);
        return m + 'm ' + s + 's';
    },

    updateDashboard: function(indexData, imageDeleteData) {
        var indexState = indexData.state || {};
        var indexStats = indexData.stats || {};
        var iDeleteState = imageDeleteData.state || {};

        var $dashboard = $('#asmi-status-dashboard');
        var $overview = $dashboard.find('.asmi-status-overview h3 .asmi-status-text');
        var $process = $dashboard.find('.asmi-process-details');
        var $summary = $dashboard.find('.asmi-last-run-summary');
        
        var $buttons = $('#asmi-reindex-button, #asmi-clear-button, #asmi-delete-images-button, #asmi-db-repair-button');

        $process.hide();
        $summary.hide();
        $buttons.prop('disabled', false);
        $('#asmi-cancel-button').hide();

        $dashboard.find('.asmi-stats-total').text(indexStats.total || 0);
        $dashboard.find('.asmi-stats-wp-total').text(indexStats.total_wp || 0);

        var activeProcess = false;

        if (iDeleteState.status === 'deleting') {
            activeProcess = true;
            $overview.html('<span class="dashicons dashicons-images-alt"></span> ' + 'Bilder werden gelöscht');
            this.renderProgressBar($process, 'Löschen der Bilder:', iDeleteState.deleted, iDeleteState.total, '#d63638', iDeleteState.started_at);
        }
        else if (indexState.status !== 'idle' && indexState.status !== 'finished') {
            activeProcess = true;
            $('#asmi-cancel-button').show();
            $overview.html('<span class="asmi-status-text"><span class="dashicons dashicons-update-alt"></span> ' + 'Import läuft</span>');
            this.renderProgressBar($process, 'Fortschritt des Imports:', indexState.processed_items, indexState.total_items, '#2271b1', indexState.started_at, indexState.current_action);
            
            var $feedContainer = $process.find('#asmi-feed-progress-container').empty().show();
            if (indexState.feed_details && indexState.feed_details.length > 0) {
               $.each(indexState.feed_details, function(i, feed) {
                   var feed_pct = feed.total > 0 ? Math.round(100 * feed.processed / feed.total) : (feed.total === 0 ? 100 : 0);
                   var html = `<p style="font-size: 12px; margin: 2px 0 2px 15px;">Feed ${i+1}: ${feed.processed} / ${feed.total} (${feed_pct}%)</p>`;
                   $feedContainer.append(html);
               });
           }
        }

        if (activeProcess) {
            $buttons.prop('disabled', true);
        } else {
            this.stopPolling();
            var lastRun = indexState.last_run || {};
            if (lastRun.type && lastRun.finished_at) {
                $summary.show();
                var statusText = lastRun.status === 'completed' ? 'erfolgreich' : (lastRun.status === 'cancelled' ? 'abgebrochen' : 'mit Fehlern');
                var summaryHtml = `
                    <li><strong>Letzte Aktion:</strong> ${lastRun.type} ${statusText} am ${this.formatDate(lastRun.finished_at)}</li>
                    <li><strong>Dauer:</strong> ${this.formatDuration(lastRun.duration)}</li>
                    <li><strong>Verarbeitet:</strong> ${lastRun.processed} | <strong>Übersprungen:</strong> ${lastRun.skipped} | <strong>Bildfehler:</strong> ${lastRun.image_errors}</li>
                `;
                $summary.find('.asmi-summary-list').html(summaryHtml);
            }
            if (indexState.error) {
                 $overview.html('<span class="dashicons dashicons-warning"></span> ' + 'Fehler');
                 $process.show().find('.asmi-state-error-p').show().find('.asmi-state-error').text(indexState.error);
            } else {
                 $overview.html('<span class="dashicons dashicons-yes-alt"></span> ' + 'Bereit');
            }
        }
    },

    updateWpDashboard: function(wpState) {
        var $dashboard = $('#asmi-wp-status-dashboard');
        var $overview = $dashboard.find('.asmi-status-overview h3 .asmi-wp-status-text');
        var $process = $dashboard.find('.asmi-wp-process-details');
        var $summary = $dashboard.find('.asmi-wp-last-run-summary');
        
        var $buttons = $('#asmi-reindex-wp-button');

        $process.hide();
        $summary.hide();
        $buttons.prop('disabled', false);
        $('#asmi-cancel-wp-button').hide();

        $dashboard.find('.asmi-wp-stats-processed').text(wpState.processed_posts || 0);
        $dashboard.find('.asmi-wp-stats-total').text(wpState.total_posts || 0);

        if (wpState.status === 'indexing') {
            $dashboard.show();
            $('#asmi-cancel-wp-button').show();
            $overview.html('<span class="dashicons dashicons-update-alt"></span> WordPress Content wird verarbeitet');
            
            this.renderWpProgressBar($process, wpState);
            $buttons.prop('disabled', true);
            
            // Update current post info
            $process.find('.asmi-wp-current-title').text(wpState.current_post_title || '-');
            $process.find('.asmi-wp-current-lang').text(wpState.current_lang || '-');
            
            // Update statistics
            $process.find('.asmi-wp-chatgpt-used').text(wpState.chatgpt_used || 0);
            $process.find('.asmi-wp-fallback-used').text(wpState.fallback_used || 0);
            $process.find('.asmi-wp-timeout-errors').text(wpState.timeout_errors || 0);
            $process.find('.asmi-wp-api-errors').text(wpState.api_errors || 0);
            $process.find('.asmi-wp-manually-imported').text(wpState.manually_imported || 0);
        } else if (wpState.status === 'finished' || wpState.last_run) {
            $dashboard.show();
            this.stopWpPolling();
            
            var lastRun = wpState.last_run || {};
            if (lastRun.type && lastRun.finished_at) {
                $summary.show();
                var statusText = lastRun.status === 'completed' ? 'erfolgreich' : (lastRun.status === 'cancelled' ? 'abgebrochen' : 'mit Fehlern');
                var summaryHtml = `
                    <li><strong>Letzte WordPress-Indexierung:</strong> ${statusText} am ${this.formatDate(lastRun.finished_at)}</li>
                    <li><strong>Dauer:</strong> ${this.formatDuration(lastRun.duration)}</li>
                    <li><strong>Posts verarbeitet:</strong> ${lastRun.processed}</li>
                    <li><strong>ChatGPT verwendet:</strong> ${lastRun.chatgpt_used} | <strong>Fallback:</strong> ${lastRun.fallback_used}</li>
                    <li><strong>Timeout-Fehler:</strong> ${lastRun.timeout_errors} | <strong>API-Fehler:</strong> ${lastRun.api_errors}</li>
                    <li><strong>Geschützte Imports:</strong> ${lastRun.manually_imported}</li>
                `;
                $summary.find('.asmi-wp-summary-list').html(summaryHtml);
            }
            
            if (wpState.error) {
                $overview.html('<span class="dashicons dashicons-warning"></span> WordPress Indexierung - Fehler');
                $process.show().find('.asmi-wp-state-error-p').show().find('.asmi-wp-state-error').text(wpState.error);
            } else {
                $overview.html('<span class="dashicons dashicons-yes-alt"></span> WordPress Content - Bereit');
            }
        } else {
            // Hide dashboard when not active
            $dashboard.hide();
        }
    },

    renderProgressBar: function($container, title, done, total, color, startTime, actionText) {
        done = parseInt(done, 10) || 0;
        total = parseInt(total, 10) || 0;
        var pct = total > 0 ? Math.round(100 * done / total) : 0;
        var $titleContainer = $container.find('p:has(.asmi-process-title)');
        $container.show();
        if (actionText) {
            $titleContainer.show();
            $container.find('.asmi-process-title').text(actionText);
        } else {
            $titleContainer.hide();
        }
        $container.find('.asmi-progress-bar-inner').css({ 'width': pct + '%', 'background-color': color });
        $container.find('.asmi-progress-label').text(title);
        $container.find('.asmi-state-done').text(done);
        $container.find('.asmi-state-total').text(total);
        $container.find('.asmi-state-pct').text(pct);
        $container.find('.asmi-state-started').text(this.formatDate(startTime));
        var duration = startTime > 0 ? this.formatDuration(Math.floor(Date.now() / 1000) - startTime) : '–';
        $container.find('.asmi-state-duration').text(duration);
        $container.find('#asmi-feed-progress-container').hide();
        $container.find('.asmi-state-error-p').hide();
    },

    renderWpProgressBar: function($container, wpState) {
        var done = parseInt(wpState.processed_posts, 10) || 0;
        var total = parseInt(wpState.total_posts, 10) || 0;
        var pct = total > 0 ? Math.round(100 * done / total) : 0;
        
        $container.show();
        
        if (wpState.current_action) {
            $container.find('.asmi-wp-process-title').text(wpState.current_action);
        }
        
        $container.find('.asmi-wp-progress-bar-inner').css({ 
            'width': pct + '%', 
            'background-color': '#0073aa' 
        });
        
        $container.find('.asmi-wp-state-done').text(done);
        $container.find('.asmi-wp-state-total').text(total);
        $container.find('.asmi-wp-state-pct').text(pct);
        $container.find('.asmi-wp-state-started').text(this.formatDate(wpState.started_at));
        
        var duration = wpState.started_at > 0 ? this.formatDuration(Math.floor(Date.now() / 1000) - wpState.started_at) : '–';
        $container.find('.asmi-wp-state-duration').text(duration);
        
        $container.find('.asmi-wp-state-error-p').hide();
    }
  };

  asmiAdmin.init();
});