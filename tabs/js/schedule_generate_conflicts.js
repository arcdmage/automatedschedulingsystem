(function () {
  window.openGeneratedSchedules = function (sectionId) {
    try {
      if (
        window.parent &&
        window.parent !== window &&
        typeof window.parent.openScheduleViewForSection === "function"
      ) {
        window.parent.openScheduleViewForSection(sectionId);
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

  // Helper to query and allow graceful degradation if an element is missing
  function $id(id) {
    return document.getElementById(id);
  }

  // DOM elements
  const form = $id("generate-form");
  const randomToggle = $id("random-mode-toggle");
  const generateBtn = $id("generate-btn");
  const progressSection = $id("progress-section");
  const progressFill = $id("progress-fill");
  const progressLog = $id("progress-log");
  const resultMessage = $id("result-message");

  // Modal elements
  const conflictModal = $id("conflictModal");
  const modalTitle = $id("modalTitle");
  const modalMessage = $id("modalMessage");
  const conflictList = $id("conflictList");
  const modalCancelBtn = $id("modalCancelBtn");
  const modalConfirmBtn = $id("modalConfirmBtn");

  // Ensure loadSection exists for the section-select onchange
  window.loadSection =
    window.loadSection ||
    function () {
      const el = $id("section-select");
      if (el) {
        const sectionId = el.value;
        if (sectionId) {
          window.location.href =
            "/mainscheduler/tabs/schedule_generate.php?section_id=" + sectionId;
        }
      }
    };

  // Basic logger into progressLog
  function addLog(message, type = "info") {
    if (!progressLog) return;
    const entry = document.createElement("div");
    entry.className = "log-entry " + type;
    entry.textContent = message;
    progressLog.appendChild(entry);
    // keep scroll at bottom
    progressLog.scrollTop = progressLog.scrollHeight;
  }

  function setProgress(percent, label) {
    if (!progressFill) return;
    progressFill.style.width = (percent | 0) + "%";
    progressFill.textContent = label != null ? label : (percent | 0) + "%";
  }

  // Show modal with conflict details, call onConfirm when user confirms
  function showConflictModal(conflicts = [], internalCount = 0, onConfirm) {
    if (!conflictModal) {
      console.warn("Conflict modal missing from DOM; cannot show conflicts.");
      return;
    }

    // Clear and populate list
    conflictList.innerHTML = "";
    if (Array.isArray(conflicts) && conflicts.length > 0) {
      const ul = document.createElement("ul");
      ul.style.margin = "0";
      ul.style.padding = "8px";
      conflicts.forEach((c) => {
        const li = document.createElement("li");
        // build readable string
        const parts = [];
        if (c.details) {
          parts.push(c.details);
        } else if (c.message) {
          parts.push(c.message);
        } else if (c.type) {
          parts.push(c.type);
        }
        li.textContent = parts.join(" — ");
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

    // Show modal
    conflictModal.style.display = "flex";
    conflictModal.setAttribute("aria-hidden", "false");

    // Handlers
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
        generateBtn.textContent = "🚀 Generate Weekly Template";
      }
    }

    function escHandler(e) {
      if (e.key === "Escape") {
        cancelHandler();
      }
    }

    modalConfirmBtn.addEventListener("click", confirmHandler);
    modalCancelBtn.addEventListener("click", cancelHandler);
    document.addEventListener("keydown", escHandler);

    // Focus confirm button for quick keyboard users
    modalConfirmBtn.focus();
  }

  // Main generator call. Accepts a `confirmForce` flag (0 or 1)
  async function callGenerator(confirmForce = 0) {
    if (!form) {
      console.error("Generation form not found");
      return;
    }
    // Prevent multiple parallel submissions.
    // Allow forced confirm runs (confirmForce === 1) to bypass the "disabled" guard
    // so the confirm -> callGenerator(1) flow can proceed even if the button was disabled.
    if (generateBtn && generateBtn.disabled && !confirmForce) {
      addLog("Generator is already running...", "info");
      return;
    }

    const isRandom = randomToggle ? randomToggle.checked : false;
    const endpoint = isRandom
      ? "/mainscheduler/tabs/actions/schedule_random_generate.php"
      : "/mainscheduler/tabs/actions/schedule_auto_generate.php";

    // Prepare form data copy so we can append confirm flags safely
    const fd = new FormData(form);
    if (confirmForce) {
      fd.set("confirm_force", "1");
    }

    // UI state
    if (generateBtn) {
      generateBtn.disabled = true;
      generateBtn.textContent = "⏳ Generating...";
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

      const resp = await fetch(endpoint, {
        method: "POST",
        body: fd,
        credentials: "same-origin",
        headers: {
          // don't set content-type; let browser set boundary for FormData
        },
      });

      const text = await resp.text();
      if (!text || text.trim() === "") {
        throw new Error("Empty response from server. Check PHP error logs.");
      }

      let json;
      try {
        json = JSON.parse(text);
      } catch (err) {
        // Show a bit of the raw response to help debugging
        const preview = text.substring(0, 1000);
        throw new Error("Invalid JSON from server. Preview:\n" + preview);
      }

      // If backend requests confirmation, show modal and wait for user
      if (json && json.needs_confirmation) {
        addLog("Conflicts detected — awaiting user confirmation...", "warning");
        setProgress(45, "Conflicts detected");

        // Show modal with conflict details. On confirm, re-call generator with confirm flag.
        showConflictModal(
          json.conflict_details || [],
          json.internal_conflicts_count || 0,
          function onConfirm() {
            addLog("User confirmed overwrite; proceeding...", "info");
            // Re-run generator with confirm force set
            callGenerator(1).catch((e) => {
              console.error(e);
              addLog("Error during forced generation: " + e.message, "error");
            });
          },
        );

        // Re-enable the button only after modal flow; don't re-enable now
        return json;
      }

      // Not asking for confirmation -> final result
      setProgress(90, "Applying changes...");
      if (json.debug_log && Array.isArray(json.debug_log)) {
        addLog("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━", "info");
        addLog("📋 DEBUG LOG (showing conflict details):", "info");
        addLog("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━", "info");
        json.debug_log.forEach((msg) => {
          // crude categorization for log styling
          if (typeof msg === "string" && msg.match(/fail|error/i)) {
            addLog(msg, "error");
          } else if (typeof msg === "string" && msg.match(/ok|✓|created/i)) {
            addLog(msg, "success");
          } else {
            addLog(msg, "info");
          }
        });
        addLog("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━", "info");
      }

      if (json.success) {
        setProgress(100, "Completed");
        addLog("✓ Generation completed successfully!", "success");
        addLog(
          `✓ Created ${json.schedules_created || 0} schedule entries`,
          "success",
        );

        // display result box
        if (resultMessage) {
          const sectionId = new FormData(form).get("section_id") || "";
          resultMessage.innerHTML = `
            <div class="alert alert-success">
              <h3>Success</h3>
              <p><strong>${json.schedules_created || 0}</strong> schedules created successfully.</p>
              ${json.conflicts_found ? `<p>⚠️ ${json.conflicts_found} conflict(s) were skipped.</p>` : ""}
              <p><a href="#" onclick="return openGeneratedSchedules('${sectionId}')" style="color:#155724; text-decoration:underline; font-weight:bold;">View Generated Schedules →</a></p>
            </div>
          `;
        }
      } else {
        // Backend returned success:false
        setProgress(100, "Failed");
        addLog(
          "✗ Generation failed: " + (json.message || "Unknown error"),
          "error",
        );
        if (resultMessage) {
          resultMessage.innerHTML = `
            <div class="alert alert-danger">
              <h3>❌ Error</h3>
              <p>${json.message || "Unknown error"}</p>
            </div>
          `;
        }
      }

      // Re-enable button after short delay
      setTimeout(() => {
        if (generateBtn) {
          generateBtn.disabled = false;
          generateBtn.textContent = "🚀 Generate Weekly Template";
        }
      }, 800);

      return json;
    } catch (err) {
      // Network or parsing error
      console.error(err);
      setProgress(100, "Error");
      addLog("✗ " + (err.message || "Unknown error"), "error");
      if (resultMessage) {
        resultMessage.innerHTML = `
          <div class="alert alert-danger">
            <h3>❌ Error</h3>
            <p>${err && err.message ? err.message.replace(/\n/g, "<br>") : "Unknown error"}</p>
          </div>
        `;
      }
      if (generateBtn) {
        generateBtn.disabled = false;
        generateBtn.textContent = "🚀 Generate Weekly Template";
      }
      return { success: false, message: err.message || "Error" };
    }
  }

  // Wire up UI events
  function init() {
    if (randomToggle) {
      randomToggle.addEventListener("change", function () {
        if (!generateBtn) return;
        generateBtn.textContent = this.checked
          ? "🎲 Generate Random Schedule"
          : "🚀 Generate Weekly Template";
      });
    }

    if (form) {
      form.addEventListener("submit", function (e) {
        e.preventDefault();
        // Start the generate flow (initial call without confirm_force)
        callGenerator(0).catch((err) => {
          console.error("Generation failed:", err);
        });
      });
    } else {
      console.warn(
        "Generation form (#generate-form) not found - schedule_generate_conflicts.js will be inactive.",
      );
    }
  }

  // Initialize after DOM is ready
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
