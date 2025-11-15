jQuery(document).ready(function($) {
    /**
     * Yardlii WPUF Card Layout
     * Handles both Single Step (JS-created cards) and Multistep (Native Fieldsets).
     */
    $('.wpuf-form-add').each(function() {
        var $form = $(this);

        // ---------------------------------------------------------
        // SCENARIO A: MULTISTEP FORM
        // ---------------------------------------------------------
        if ($form.find('.wpuf-multistep-fieldset').length > 0) {
            $form.addClass('yardlii-multistep-cards-active');
            
            $form.find('.wpuf-multistep-fieldset').each(function() {
                var $fs = $(this);

                // 1. Identify parts
                var $buttons = $fs.find('.multistep-button-area');
                var $legend = $fs.find('legend');
                // Select all LIs that are direct children (the fields)
                var $fields = $fs.find('> li.wpuf-el');

                // 2. Wrap fields in a content body if not already wrapped
                if ($fields.length > 0 && $fs.find('.yardlii-multistep-body').length === 0) {
                    $fields.wrapAll('<div class="yardlii-multistep-body"></div>');
                }
                
                // 3. Move Button Area to the very bottom
                // (WPUF puts it at the top by default in the DOM)
                if ($buttons.length > 0) {
                    $fs.append($buttons);
                }
            });
            
            // We do not run the Single Step logic below if this is multistep
            return; 
        }

        // ---------------------------------------------------------
        // SCENARIO B: SINGLE STEP FORM
        // ---------------------------------------------------------
        var $mainList = $form.find('ul.wpuf-form');
        var $allFields = $mainList.length ? $mainList.find('> li') : $form.find('> li');
        
        if ($allFields.length === 0) return;

        var hasSectionBreak = $form.find('li.section_break, li[class*="section_break"]').length > 0 || $form.find('.wpuf-section-wrap').length > 0;
        
        if (!hasSectionBreak) {
            if ($mainList.length) {
                $mainList.wrap('<div class="yardlii-form-cards-wrapper"><div class="yardlii-card"><div class="yardlii-card-inner"></div></div></div>');
            } else {
                $form.wrapInner('<div class="yardlii-form-cards-wrapper"><div class="yardlii-card"><ul class="wpuf-form"></ul></div></div>');
            }
            return;
        }

        // Build Cards (Existing Logic)
        var $wrapper = $('<div class="yardlii-form-cards-wrapper"></div>');
        var $currentCard = null;
        var $currentList = null;

        function createCard() {
            var $card = $('<div class="yardlii-card"></div>');
            var $ul = $('<ul class="wpuf-form form-label-above"></ul>'); 
            $card.append($ul);
            return { card: $card, list: $ul };
        }

        var current = createCard();
        $currentCard = current.card;
        $currentList = current.list;
        $wrapper.append($currentCard);

        $allFields.each(function() {
            var $field = $(this);

            if ($field.hasClass('wpuf-submit')) {
                $currentList.append($field);
                return;
            }

            var isBreak = $field.hasClass('section_break') || 
                          $field.attr('class').indexOf('section_break') !== -1 || 
                          $field.find('.wpuf-section-title').length > 0;

            if (isBreak) {
                current = createCard();
                $currentCard = current.card;
                $currentList = current.list;
                $wrapper.append($currentCard);
                $currentList.append($field);
                $field.addClass('section_break'); 
            } else {
                $currentList.append($field);
            }
        });

        if ($mainList.length) {
            $mainList.replaceWith($wrapper);
        } else {
            $form.html($wrapper);
        }
        
        $wrapper.find('.yardlii-card').each(function() {
            if ($(this).find('li').length === 0) $(this).remove();
        });
        
        $(document).trigger('yardlii_cards_rendered');
    });
});