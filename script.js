const tabs = document.querySelectorAll("[data-tab-target]");
const tabContents = document.querySelectorAll("[data-tab-content]");
const TAB_MEMORY_KEY = "mainscheduler-active-tab";

function dispatchTabEvent(target, eventName) {
  try {
    target.dispatchEvent(
      new CustomEvent(eventName, { detail: { id: target.id } }),
    );
  } catch (e) {
    // ignore if CustomEvent not supported
  }
}

function saveActiveTab(id) {
  if (!id || typeof sessionStorage === "undefined") return;
  try {
    sessionStorage.setItem(TAB_MEMORY_KEY, id);
  } catch (e) {
    // ignore storage exceptions (incognito or restricted modes)
  }
}

function getSavedTabId() {
  if (typeof sessionStorage === "undefined") return null;
  try {
    return sessionStorage.getItem(TAB_MEMORY_KEY);
  } catch (e) {
    return null;
  }
}

function activateTab(tab) {
  if (!tab) return;
  const target = document.querySelector(tab.dataset.tabTarget);
  if (!target) {
    console.error("Tab target not found for selector:", tab.dataset.tabTarget);
    return;
  }

  const prevContent = document.querySelector("[data-tab-content].active");
  const prevTab = document.querySelector("[data-tab-target].active");

  if (prevContent && prevContent !== target) {
    prevContent.classList.remove("active");
    dispatchTabEvent(prevContent, "tab-hidden");
  } else {
    tabContents.forEach((tabContent) => {
      if (tabContent !== target) tabContent.classList.remove("active");
    });
  }

  if (prevTab && prevTab !== tab) {
    prevTab.classList.remove("active");
  } else {
    tabs.forEach((t) => {
      if (t !== tab) t.classList.remove("active");
    });
  }

  tab.classList.add("active");
  target.classList.add("active");
  saveActiveTab(target.id);
  dispatchTabEvent(target, "tab-shown");
}

tabs.forEach((tab) => {
  tab.addEventListener("click", () => activateTab(tab));
});

const savedTabId = getSavedTabId();
if (savedTabId) {
  const savedTab = document.querySelector(`[data-tab-target="#${savedTabId}"]`);
  if (savedTab) {
    activateTab(savedTab);
  }
}
