jQuery(document).ready(function($) {
    /**
     * Yardlii WPUF Card Layout
     * Targets Single Step WPUF forms and groups fields into Cards.
     */
    $('.wpuf-form-add').each(function() {
        var $form = $(this);

        // 1. LOCATE FIELD LIST
        // Single step forms typically use a UL container
        var $mainList = $form.find('ul.wpuf-form');
        var $allFields = $mainList.length ? $mainList.find('> li') : $form.find('> li');
        
        // If no fields found, stop.
        if ($allFields.length === 0) return;

        // 2. CHECK FOR BREAKS
        // Only run if there is at least one section break to define groups
        // OR if we want to wrap the whole form in one card for consistency
        var hasSectionBreak = $form.find('li.section_break, li[class*="section_break"]').length > 0 || $form.find('.wpuf-section-wrap').length > 0;
        
        if (!hasSectionBreak) {
            // Wrap single-step forms WITHOUT breaks in one big card for consistency
            if ($mainList.length) {
                $mainList.wrap('<div class="yardlii-form-cards-wrapper"><div class="yardlii-card"></div></div>');
            } else {
                $form.wrapInner('<div class="yardlii-form-cards-wrapper"><div class="yardlii-card"><ul class="wpuf-form"></ul></div></div>');
            }
            return;
        }

        // 3. BUILD CARDS
        var $wrapper = $('<div class="yardlii-form-cards-wrapper"></div>');
        var $currentCard = null;
        var $currentList = null;

        function createCard() {
            var $card = $('<div class="yardlii-card"></div>');
            var $ul = $('<ul class="wpuf-form form-label-above"></ul>'); 
            $card.append($ul);
            return { card: $card, list: $ul };
        }

        // Init first card
        var current = createCard();
        $currentCard = current.card;
        $currentList = current.list;
        $wrapper.append($currentCard);

        $allFields.each(function() {
            var $field = $(this);

            // Handle Submit Button (Keep it in the flow of the last card)
            if ($field.hasClass('wpuf-submit')) {
                $currentList.append($field);
                return;
            }

            // Check for Section Break
            var isBreak = $field.hasClass('section_break') || 
                          $field.attr('class').indexOf('section_break') !== -1 || 
                          $field.find('.wpuf-section-title').length > 0;

            if (isBreak) {
                // Start new card
                current = createCard();
                $currentCard = current.card;
                $currentList = current.list;
                $wrapper.append($currentCard);
                
                $currentList.append($field);
                $field.addClass('section_break'); // Ensure class exists for CSS
            } else {
                $currentList.append($field);
            }
        });

        // 4. APPLY DOM CHANGES
        if ($mainList.length) {
            $mainList.replaceWith($wrapper);
        } else {
            $form.html($wrapper);
        }
        
        // Cleanup empty cards
        $wrapper.find('.yardlii-card').each(function() {
            if ($(this).find('li').length === 0) $(this).remove();
        });
        
        $(document).trigger('yardlii_cards_rendered');
    });
});