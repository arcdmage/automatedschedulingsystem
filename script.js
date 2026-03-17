const tabs = document.querySelectorAll("[data-tab-target]"); //ability to select data
const tabContents = document.querySelectorAll("[data-tab-content]"); //shows tab contents

tabs.forEach((tab) => {
  //if click then tab will be active, if click another tab then it will deactivate previously activated tab and activate the new one.
  tab.addEventListener("click", () => {
    const target = document.querySelector(tab.dataset.tabTarget);

    // capture previous active content and tab (if any)
    const prevContent = document.querySelector("[data-tab-content].active");
    const prevTab = document.querySelector("[data-tab-target].active");

    // If there is a previous content and it's not the same as the target, hide it and dispatch tab-hidden
    if (prevContent && prevContent !== target) {
      prevContent.classList.remove("active");
      try {
        prevContent.dispatchEvent(
          new CustomEvent("tab-hidden", { detail: { id: prevContent.id } }),
        );
      } catch (e) {
        // ignore if CustomEvent not supported (very old browsers)
      }
    } else {
      // fallback: ensure all contents are cleared
      tabContents.forEach((tabContent) => {
        if (tabContent !== target) tabContent.classList.remove("active");
      });
    }

    // Remove active state from previous tab control if it's different
    if (prevTab && prevTab !== tab) {
      prevTab.classList.remove("active");
    } else {
      // fallback: clear all tab controls
      tabs.forEach((t) => {
        if (t !== tab) t.classList.remove("active");
      });
    }

    // Activate the clicked tab
    tab.classList.add("active");

    if (!target) {
      console.error(
        "Tab target not found for selector:",
        tab.dataset.tabTarget,
      );
      return;
    }

    // Show selected content
    target.classList.add("active");

    // Dispatch tab-shown for the newly activated content
    try {
      target.dispatchEvent(
        new CustomEvent("tab-shown", { detail: { id: target.id } }),
      );
    } catch (e) {
      // ignore if CustomEvent not supported
    }
  });
});
