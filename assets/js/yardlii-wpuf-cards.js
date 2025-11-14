jQuery(document).ready(function($) {
    /**
     * Yardlii WPUF Card Layout
     * Converts flat WPUF lists into grouped "Card" sections.
     */
    $('.wpuf-form-add').each(function() {
        var $form = $(this);

        // 1. CHECK FOR MULTISTEP
        // If this is a Multistep form, WPUF already groups fields into <fieldset> tags.
        // Our CSS handles the styling. We do NOT want to restructure the DOM here.
        if ($form.find('.wpuf-multistep-fieldset').length > 0) {
            $form.addClass('yardlii-multistep-cards-active'); // Helper class for CSS
            return; // EXIT SCRIPT
        }

        // 2. LOCATE FIELD LIST (Single Step)
        // Fields are usually inside <ul class="wpuf-form">
        var $mainList = $form.find('ul.wpuf-form');
        
        // Fallback: If no UL found, try direct children (unlikely in standard WPUF but possible in custom themes)
        var $allFields = $mainList.length ? $mainList.find('> li') : $form.find('> li');
        
        // If no fields found, don't run
        if ($allFields.length === 0) return;

        // Only run if there is at least one section break to define groups
        // OR if we want to wrap the whole form in one card for consistency
        var hasSectionBreak = $form.find('li.section_break').length > 0 || $form.find('.wpuf-section-wrap').length > 0;
        
        if (!hasSectionBreak) {
            // Optional: Wrap single-step forms WITHOUT breaks in one big card for consistency
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

            // Handle Submit Button (keep in flow or last card)
            if ($field.hasClass('wpuf-submit')) {
                $currentList.append($field);
                return;
            }

            // Check for Section Break (Class or internal structure)
            var isBreak = $field.hasClass('section_break') || $field.find('.wpuf-section-title').length > 0;

            if (isBreak) {
                // Start new card
                current = createCard();
                $currentCard = current.card;
                $currentList = current.list;
                $wrapper.append($currentCard);
                
                // Add the header to the new card
                $currentList.append($field);
                // Ensure it has the class for CSS styling (fix for some WPUF versions using dynamic classes)
                $field.addClass('section_break'); 
            } else {
                $currentList.append($field);
            }
        });

        // 4. APPLY DOM CHANGES
        // We replace the <ul> with our card wrapper, preserving surrounding form elements
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