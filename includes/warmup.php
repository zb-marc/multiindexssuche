<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Cron: Feed-Cache vorwärmen
add_action('asmi_cron_warmup', function(){
  $o = asmi_get_opts();
  $feeds = array_filter(array_map('trim', explode(',', $o['feed_urls'])));
  foreach ($feeds as $u){
    delete_transient('asmi_'.md5($u));
    asmi_fetch_items($u,$o);
  }
});