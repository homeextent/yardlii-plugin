jQuery(document).ready(function($) {
    /**
     * Yardlii WPUF Card Layout
     * Converts flat WPUF lists into grouped "Card" sections.
     */
    $('.wpuf-form-add').each(function() {
        var $form = $(this);
        var $allFields = $form.find('> li');
        
        // If no fields or no section breaks, don't run to avoid layout breakage
        if ($allFields.length === 0) return;

        // Create a container to hold our cards
        var $wrapper = $('<div class="yardlii-form-cards-wrapper"></div>');
        
        // Variables for loop
        var $currentCard = null;
        var $currentList = null;

        // Helper to start a new card
        function createCard() {
            var $card = $('<div class="yardlii-card"></div>');
            var $ul = $('<ul class="wpuf-form"></ul>'); // Maintain class for WPUF CSS compatibility
            $card.append($ul);
            return { card: $card, list: $ul };
        }

        // Init first card (for fields before the first section break)
        var current = createCard();
        $currentCard = current.card;
        $currentList = current.list;
        $wrapper.append($currentCard);

        $allFields.each(function() {
            var $field = $(this);

            // If it's a submit area, move it out of cards entirely (or keep in last card)
            // Usually WPUF puts submit in `li.wpuf-submit`. We want that separate or sticky.
            if ($field.hasClass('wpuf-submit')) {
                // We will handle submit button separately in CSS/JS if needed
                // For now, append to the last card list
                $currentList.append($field);
                return;
            }

            // Is this a Section Break?
            if ($field.hasClass('section_break')) {
                // Start a new card
                current = createCard();
                $currentCard = current.card;
                $currentList = current.list;
                $wrapper.append($currentCard);
                
                // Add the section break (header) to the new list
                $currentList.append($field);
            } else {
                // Regular field, append to current list
                $currentList.append($field);
            }
        });

        // Replace original UL with our new structure
        $form.html($wrapper);
        
        // Cleanup: Remove empty cards (if any)
        $wrapper.find('.yardlii-card').each(function() {
            if ($(this).find('li').length === 0) $(this).remove();
        });
        
        // Trigger a layout ready event
        $(document).trigger('yardlii_cards_rendered');
    });
});