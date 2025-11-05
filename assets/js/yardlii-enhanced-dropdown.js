jQuery(function($) {
  console.log("Yardlii Enhanced Dropdown – Accordion with Resettable Select Button");

  const dropdown = $("select#group");
  if (!dropdown.length) return;

  // Hide original select
  dropdown.hide();

  // Build wrapper + button + menu
  const wrapper = $('<div class="yardlii-enhanced-dropdown"></div>');
  const defaultLabel = "Select Group";
  const trigger = $('<button type="button" class="dropdown-trigger">' + defaultLabel + "</button>");
  const menu = $('<div class="dropdown-menu"></div>');
  wrapper.append(trigger).append(menu);
  dropdown.after(wrapper);

  // Build parent-child structure
  const parentMap = {};
  dropdown.find("option").each(function() {
    const t = $(this),
      txt = t.text().trim(),
      val = t.val(),
      lvl = t.attr("class") || "level-0";
    if (val == -1) return;
    if (lvl.includes("level-0")) {
      parentMap[val] = { text: txt, children: [] };
    } else if (lvl.includes("level-1")) {
      const pval = t.prevAll("option.level-0:first").val();
      if (pval && parentMap[pval]) parentMap[pval].children.push({ text: txt, value: val });
    }
  });

  // Create DOM elements
  $.each(parentMap, function(pval, pdata) {
    const parent = $('<div class="dropdown-item parent" data-value="' + pval + '">' + pdata.text + "</div>");
    const childContainer = $('<div class="child-container"></div>');
    pdata.children.forEach(ch => {
      childContainer.append('<div class="dropdown-item child" data-value="' + ch.value + '">' + ch.text + "</div>");
    });
    menu.append(parent).append(childContainer);
  });

  // Toggle dropdown visibility
  trigger.on("click", function(e) {
    e.stopPropagation();
    $(".yardlii-enhanced-dropdown .dropdown-menu").not(menu).slideUp(150);
    menu.slideToggle(200);
  });

  // Accordion behavior (open down only)
  menu.on("click", ".dropdown-item.parent", function(e) {
    e.stopPropagation();
    const parent = $(this);
    const childContainer = parent.next(".child-container");
    const open = parent.hasClass("expanded");

    $(".dropdown-item.parent.expanded").not(parent)
      .removeClass("expanded")
      .next(".child-container")
      .slideUp(200);

    if (open) {
      parent.removeClass("expanded");
      childContainer.slideUp(200);
    } else {
      parent.addClass("expanded");
      childContainer.slideDown(220);
    }
  });

  // Track last selected child
  let lastSelectedValue = null;

  // Child click — select or deselect
  menu.on("click", ".dropdown-item.child", function(e) {
    e.stopPropagation();
    const child = $(this);
    const value = child.data("value");
    const text = child.text();

    if (lastSelectedValue === value) {
      // Deselect current value
      dropdown.val("").trigger("change");
      menu.find(".dropdown-item.child").removeClass("selected");
      trigger.text(defaultLabel);
      lastSelectedValue = null;
    } else {
      // Select new value
      dropdown.val(value).trigger("change");
      menu.find(".dropdown-item.child").removeClass("selected");
      child.addClass("selected");
      trigger.text(text);
      lastSelectedValue = value;
    }

    // Close dropdown
    $(".dropdown-item.parent.expanded").removeClass("expanded").next(".child-container").slideUp(150);
    menu.slideUp(150);
  });

  // Click outside closes + resets if nothing chosen
  $(document).on("click", function(e) {
    if (!$(e.target).closest(".yardlii-enhanced-dropdown").length) {
      $(".dropdown-menu").slideUp(150);
      $(".dropdown-item.parent.expanded").removeClass("expanded").next(".child-container").slideUp(150);

      // If no child selected, reset button text
      if (!lastSelectedValue) {
        trigger.text(defaultLabel);
      }
    }
  });
});
