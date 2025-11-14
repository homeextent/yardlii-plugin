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

        // 2. STANDARD FORM LOGIC (For single-step forms)
        var $allFields = $form.find('> li');
        
        // If no fields or no section breaks, don't run
        if ($allFields.length === 0) return;

        // Only run if there is at least one section break to define groups
        if ($form.find('li.section_break').length === 0) {
            // Optional: If you want single-step forms WITHOUT breaks to still be one big card:
            $form.wrapInner('<div class="yardlii-form-cards-wrapper"><div class="yardlii-card"><ul class="wpuf-form"></ul></div></div>');
            return;
        }

        // Create a container
        var $wrapper = $('<div class="yardlii-form-cards-wrapper"></div>');
        
        var $currentCard = null;
        var $currentList = null;

        function createCard() {
            var $card = $('<div class="yardlii-card"></div>');
            var $ul = $('<ul class="wpuf-form"></ul>'); 
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

            if ($field.hasClass('wpuf-submit')) {
                $currentList.append($field);
                return;
            }

            if ($field.hasClass('section_break')) {
                current = createCard();
                $currentCard = current.card;
                $currentList = current.list;
                $wrapper.append($currentCard);
                $currentList.append($field);
            } else {
                $currentList.append($field);
            }
        });

        $form.html($wrapper);
        
        $wrapper.find('.yardlii-card').each(function() {
            if ($(this).find('li').length === 0) $(this).remove();
        });
        
        $(document).trigger('yardlii_cards_rendered');
    });
});