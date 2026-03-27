(function () {
  window.openGeneratedSchedules = function (sectionId) {
    try {
      if (
        window.parent &&
        window.parent !== window &&
        typeof window.parent.openScheduleViewForSection === "function"
      ) {
        window.parent.openScheduleViewForSection(sectionId, "manual");
        return false;
      }
    } catch (err) {
      console.error("Unable to access parent schedule view handler:", err);
    }

    window.location.href =
      "/mainscheduler/tabs/schedule_view.php?section_id=" +
      encodeURIComponent(sectionId || "");
    return false;
  };

  function $id(id) {
    return document.getElementById(id);
  }

  const form = $id("generate-form");
  const randomToggle = $id("random-mode-toggle");
  const generateBtn = $id("generate-btn");
  const progressSection = $id("progress-section");
  const progressFill = $id("progress-fill");
  const progressLog = $id("progress-log");
  const resultMessage = $id("result-message");
  const conflictModal = $id("conflictModal");
  const conflictList = $id("conflictList");
  const modalCancelBtn = $id("modalCancelBtn");
  const modalConfirmBtn = $id("modalConfirmBtn");

  window.loadSection = window.loadSection || function () {
    const el = $id("section-select");
    if (el) {
      const sectionId = el.value;
      if (sectionId) {
        window.location.href =
          "/mainscheduler/tabs/schedule_generate.php?section_id=" + sectionId;
      }
    }
  };

  function addLog(message, type = "info") {
    if (!progressLog) return;
    const entry = document.createElement("div");
    entry.className = "log-entry " + type;
    entry.textContent = message;
    progressLog.appendChild(entry);
    progressLog.scrollTop = progressLog.scrollHeight;
  }

  function setProgress(percent, label) {
    if (!progressFill) return;
    progressFill.style.width = (percent | 0) + "%";
    progressFill.textContent = label != null ? label : (percent | 0) + "%";
  }

  function showConflictModal(conflicts = [], internalCount = 0, onConfirm) {
    if (!conflictModal) return;

    conflictList.innerHTML = "";
    if (Array.isArray(conflicts) && conflicts.length > 0) {
      const ul = document.createElement("ul");
      ul.style.margin = "0";
      ul.style.padding = "8px";
      conflicts.forEach((c) => {
        const li = document.createElement("li");
        const parts = [];
        if (c.details) parts.push(c.details);
        else if (c.message) parts.push(c.message);
        else if (c.type) parts.push(c.type);
        li.textContent = parts.join(" - ");
        li.style.marginBottom = "6px";
        ul.appendChild(li);
      });
      conflictList.appendChild(ul);
    } else {
      const p = document.createElement("div");
      p.textContent = "No external conflicts listed.";
      p.style.color = "#444";
      conflictList.appendChild(p);
    }

    const info = document.createElement("div");
    info.style.fontSize = "0.9em";
    info.style.color = "#666";
    info.style.marginTop = "8px";
    info.textContent = `Internal conflicts (skipped during generation): ${internalCount || 0}`;
    conflictList.appendChild(info);

    conflictModal.style.display = "flex";
    conflictModal.setAttribute("aria-hidden", "false");

    function cleanup() {
      conflictModal.style.display = "none";
      conflictModal.setAttribute("aria-hidden", "true");
      modalConfirmBtn.removeEventListener("click", confirmHandler);
      modalCancelBtn.removeEventListener("click", cancelHandler);
      document.removeEventListener("keydown", escHandler);
    }

    function confirmHandler() {
      cleanup();
      if (typeof onConfirm === "function") onConfirm();
    }

    function cancelHandler() {
      cleanup();
      addLog("Generation cancelled by user.", "info");
      if (generateBtn) {
        generateBtn.disabled = false;
        generateBtn.textContent = "Generate Weekly Template";
      }
    }

    function escHandler(e) {
      if (e.key === "Escape") cancelHandler();
    }

    modalConfirmBtn.addEventListener("click", confirmHandler);
    modalCancelBtn.addEventListener("click", cancelHandler);
    document.addEventListener("keydown", escHandler);
    modalConfirmBtn.focus();
  }

  async function callGenerator(confirmForce = 0) {
    if (!form) return;
    if (generateBtn && generateBtn.disabled && !confirmForce) {
      addLog("Generator is already running...", "info");
      return;
    }

    const isRandom = randomToggle ? randomToggle.checked : false;
    const endpoint = isRandom
      ? "/mainscheduler/tabs/actions/schedule_random_generate.php"
      : "/mainscheduler/tabs/actions/schedule_auto_generate.php";

    const fd = new FormData(form);
    if (confirmForce) fd.set("confirm_force", "1");

    if (generateBtn) {
      generateBtn.disabled = true;
      generateBtn.textContent = "Generating...";
    }
    if (progressSection) progressSection.classList.add("active");
    setProgress(10, "Starting...");
    addLog(
      isRandom
        ? "Random mode: distributing subjects..."
        : "Pattern mode: applying saved patterns...",
      "info",
    );

    try {
      setProgress(30, "Checking conflicts...");
      addLog("Sending generation request to server...", "info");

      const resp = await fetch(endpoint, { method: "POST", body: fd, credentials: "same-origin" });
      const text = await resp.text();
      if (!text || text.trim() === "") throw new Error("Empty response from server.");
      const json = JSON.parse(text);

      if (json && json.needs_confirmation) {
        addLog("Conflicts detected - awaiting user confirmation...", "warning");
        setProgress(45, "Conflicts detected");
        showConflictModal(json.conflict_details || [], json.internal_conflicts_count || 0, function () {
          addLog("User confirmed overwrite; proceeding...", "info");
          callGenerator(1);
        });
        return json;
      }

      setProgress(90, "Applying changes...");
      if (json.debug_log && Array.isArray(json.debug_log)) {
        json.debug_log.forEach((msg) => addLog(String(msg), "info"));
      }

      if (json.success) {
        setProgress(100, "Completed");
        addLog("Generation completed successfully.", "success");
        addLog(`Created ${json.schedules_created || 0} schedule entries`, "success");
        if (resultMessage) {
          const sectionId = new FormData(form).get("section_id") || "";
          resultMessage.innerHTML = `
            <div class="alert alert-success">
              <h3>Success</h3>
              <p><strong>${json.schedules_created || 0}</strong> schedules created successfully.</p>
              ${json.conflicts_found ? `<p>${json.conflicts_found} conflict(s) were skipped.</p>` : ""}
              <p><a href="#" onclick="return openGeneratedSchedules('${sectionId}')" style="color:#155724; text-decoration:underline; font-weight:bold;">View Generated Schedules</a></p>
            </div>
          `;
        }
      } else {
        throw new Error(json.message || "Unknown error");
      }
    } catch (err) {
      console.error(err);
      setProgress(100, "Error");
      addLog(err.message || "Unknown error", "error");
      if (resultMessage) {
        resultMessage.innerHTML = `
          <div class="alert alert-danger">
            <h3>Error</h3>
            <p>${err && err.message ? err.message.replace(/\n/g, "<br>") : "Unknown error"}</p>
          </div>
        `;
      }
    } finally {
      if (generateBtn) {
        generateBtn.disabled = false;
        generateBtn.textContent = randomToggle && randomToggle.checked ? "Generate Random Schedule" : "Generate Weekly Template";
      }
    }
  }

  function init() {
    if (randomToggle) {
      randomToggle.addEventListener("change", function () {
        if (generateBtn) {
          generateBtn.textContent = this.checked ? "Generate Random Schedule" : "Generate Weekly Template";
        }
      });
    }
    if (form) {
      form.addEventListener("submit", function (e) {
        e.preventDefault();
        callGenerator(0);
      });
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
