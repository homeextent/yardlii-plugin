<div id="yardlii-tv-preview-overlay" style="display:none;">
  <div id="yardlii-tv-preview-modal" role="dialog" aria-modal="true" aria-labelledby="yardlii-tv-preview-title">
    <button type="button" class="close" aria-label="<?php esc_attr_e('Close preview', 'yardlii-core'); ?>" data-action="tv-preview-close">×</button>
    <h3 id="yardlii-tv-preview-title"><?php esc_html_e('Email Preview', 'yardlii-core'); ?></h3>
    <div id="yardlii-tv-preview-body"><p><?php esc_html_e('Loading…', 'yardlii-core'); ?></p></div>
  </div>
  <style>
    #yardlii-tv-preview-overlay { position:fixed; inset:0; background:rgba(0,0,0,.6); z-index:100000; display:flex; align-items:center; justify-content:center; }
    #yardlii-tv-preview-modal { background:#fff; border-radius:6px; max-width:800px; width:90%; padding:20px; position:relative; }
    #yardlii-tv-preview-modal .close { position:absolute; right:12px; top:8px; border:0; background:none; font-size:24px; cursor:pointer; }
    #yardlii-tv-preview-body { max-height:60vh; overflow:auto; border:1px solid #e0e0e0; padding:12px; background:#fff; }
  </style>
</div>
