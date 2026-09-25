// ============================================
// Profile page — logic
// ============================================

// ============================================
// Password strength rules (same as reset-password)
// ============================================
const PROFILE_RULES = {
  length: (v) => v.length >= 8,
  upper: (v) => /[A-Z]/.test(v),
  lower: (v) => /[a-z]/.test(v),
  number: (v) => /[0-9]/.test(v),
  special: (v) => /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(v),
};

function scoreProfilePassword(pw) {
  let score = 0;
  Object.values(PROFILE_RULES).forEach((fn) => {
    if (fn(pw)) score++;
  });
  return score;
}

function checkProfilePasswordStrength() {
  const pwInput = document.getElementById("newPassword");
  if (!pwInput) return;
  const pw = pwInput.value;

  const wrap = document.getElementById("pwStrengthWrap");
  const bars = document.querySelectorAll(".pw-strength-bar");
  const label = document.getElementById("pwStrengthLabel");
  const hint = document.getElementById("pwStrengthHint");
  const reqList = document.getElementById("pwRequirements");

  // Empty — reset UI
  if (pw.length === 0) {
    if (wrap) wrap.classList.add("hidden");
    if (reqList) {
      reqList.querySelectorAll(".pw-req").forEach((li) => {
        li.classList.remove("is-ok");
      });
    }
    checkProfilePasswordMatch();
    return;
  }

  if (wrap) wrap.classList.remove("hidden");

  // Evaluate rules
  let passed = 0;
  if (reqList) {
    reqList.querySelectorAll(".pw-req").forEach((li) => {
      const key = li.dataset.req;
      const ok = PROFILE_RULES[key] ? PROFILE_RULES[key](pw) : false;
      if (ok) {
        passed++;
        li.classList.add("is-ok");
      } else {
        li.classList.remove("is-ok");
      }
    });
  }

  // Bar colors/label based on passed count (0–5)
  let barColor = "bg-gray-200";
  let levelLabel = "Very Weak";
  let levelHint = "Add more variety";
  let labelColor = "text-gray-500";

  if (passed <= 1) {
    barColor = "bg-red-500";
    levelLabel = "Very Weak";
    levelHint = "Add uppercase, numbers, or symbols";
  } else if (passed === 2) {
    barColor = "bg-orange-500";
    levelLabel = "Weak";
    levelHint = "Add numbers or symbols";
  } else if (passed === 3) {
    barColor = "bg-yellow-500";
    levelLabel = "Fair";
    levelHint = "Almost there";
  } else if (passed === 4) {
    barColor = "bg-[#0F5E3D]";
    levelLabel = "Strong";
    levelHint = "Great password!";
    labelColor = "text-[#0F5E3D]";
  } else if (passed === 5) {
    barColor = "bg-[#0F5E3D]";
    levelLabel = "Very Strong";
    levelHint = "Excellent!";
    labelColor = "text-[#0F5E3D]";
  }

  // Fill 4 bars proportionally to 0–5 score
  const filledBars = Math.min(4, Math.ceil((passed / 5) * 4));
  bars.forEach((bar, i) => {
    bar.className =
      "pw-strength-bar h-1 flex-1 rounded-full transition-all " +
      (i < filledBars ? barColor : "bg-gray-200");
  });

  if (label) {
    label.textContent = "Strength: " + levelLabel;
    label.className = "text-[11px] font-medium " + labelColor;
  }
  if (hint) hint.textContent = levelHint;

  checkProfilePasswordMatch();
}

function checkProfilePasswordMatch() {
  const pwInput = document.getElementById("newPassword");
  const confirmInput = document.getElementById("confirmPassword");
  const hint = document.getElementById("pwMatchHint");

  if (!pwInput || !confirmInput || !hint) return;

  const pw = pwInput.value;
  const confirm = confirmInput.value;

  if (confirm.length === 0) {
    hint.classList.add("hidden");
    return;
  }

  hint.classList.remove("hidden");
  if (pw === confirm) {
    hint.textContent = "✓ Passwords match";
    hint.className = "mt-1.5 text-[11px] text-[#0F5E3D]";
  } else {
    hint.textContent = "✕ Passwords do not match";
    hint.className = "mt-1.5 text-[11px] text-red-600";
  }
}

// Tab switcher
function switchProfileTab(tab) {
  // Update buttons
  document.querySelectorAll(".profile-tab").forEach((btn) => {
    btn.className =
      "profile-tab shrink-0 px-5 py-2.5 text-sm font-medium text-[#2C3E50]/60 border-b-2 border-transparent hover:text-[#2C3E50] transition";
  });
  const activeBtn = document.getElementById("tab" + capitalize(tab));
  if (activeBtn) {
    activeBtn.className =
      "profile-tab shrink-0 px-5 py-2.5 text-sm font-medium text-[#0F5E3D] border-b-2 border-[#0F5E3D] transition";
  }

  // Show/hide panels
  document
    .querySelectorAll(".profile-panel")
    .forEach((p) => p.classList.add("hidden"));
  const panel = document.getElementById("panel" + capitalize(tab));
  if (panel) panel.classList.remove("hidden");
}

function capitalize(str) {
  return str.charAt(0).toUpperCase() + str.slice(1);
}

// Toggle password visibility
function toggleProfilePasswords() {
  const checkbox = document.getElementById("showPasswords");
  if (!checkbox) return;
  const type = checkbox.checked ? "text" : "password";
  ["currentPassword", "newPassword", "confirmPassword"].forEach((id) => {
    const el = document.getElementById(id);
    if (el) el.type = type;
  });
}

// ============================================
// Profile dropdown in top bar
// ============================================
function toggleProfileMenu() {
  const menu = document.getElementById("profileMenu");
  if (!menu) return;
  menu.classList.toggle("hidden");

  // Close on outside click
  if (!menu.classList.contains("hidden")) {
    setTimeout(() => {
      document.addEventListener("click", closeProfileOnOutside, {
        once: true,
      });
    }, 0);
  }
}

function closeProfileMenu() {
  const menu = document.getElementById("profileMenu");
  if (menu) menu.classList.add("hidden");
}

function closeProfileOnOutside(e) {
  const wrap = document.getElementById("profileWrap");
  if (wrap && !wrap.contains(e.target)) {
    closeProfileMenu();
  } else {
    document.addEventListener("click", closeProfileOnOutside, {
      once: true,
    });
  }
}

// ============================================
// Form submissions
// ============================================
document.addEventListener("DOMContentLoaded", () => {
  if (typeof initIcons === "function") initIcons();

  // ---- Attach password strength listeners ----
  const newPwInput = document.getElementById("newPassword");
  const confirmPwInput = document.getElementById("confirmPassword");

  if (newPwInput) {
    newPwInput.addEventListener("input", checkProfilePasswordStrength);
    newPwInput.addEventListener("keyup", checkProfilePasswordStrength);
    newPwInput.addEventListener("change", checkProfilePasswordStrength);
  }

  if (confirmPwInput) {
    confirmPwInput.addEventListener("input", checkProfilePasswordMatch);
    confirmPwInput.addEventListener("keyup", checkProfilePasswordMatch);
    confirmPwInput.addEventListener("change", checkProfilePasswordMatch);
  }

  // ---- Info form ----
  document
    .getElementById("profileInfoForm")
    ?.addEventListener("submit", async function (e) {
      e.preventDefault();

      const first = document.getElementById("profFirstName").value.trim();
      const last = document.getElementById("profLastName").value.trim();

      if (!first) {
        markInvalid("profFirstName");
        return;
      }
      if (!last) {
        markInvalid("profLastName");
        return;
      }

      const fd = new FormData(this);
      fd.append("action", "update_info");

      showLoading("Saving...", "Updating your profile information.");

      try {
        const res = await fetch("/api/profile", { method: "POST", body: fd });
        const data = await res.json();

        await new Promise((r) => setTimeout(r, 500));
        hideLoading();

        if (data.success) {
          document.getElementById("successTitle").textContent =
            "Profile Updated";
          document.getElementById("successMessage").textContent =
            data.message || "Your information has been saved.";
          showModal("successModal", "successCard");
          if (typeof initIcons === "function") initIcons();
        } else if (data.error) {
          showError("Could not save changes", data.error);
        }
      } catch (err) {
        hideLoading();
        console.error(err);
        showError("Connection error", "Unable to reach the server.");
      }
    });

  // ---- Password form ----
  document
    .getElementById("profilePasswordForm")
    ?.addEventListener("submit", async function (e) {
      e.preventDefault();

      const current = document.getElementById("currentPassword").value;
      const newPass = document.getElementById("newPassword").value;
      const confirm = document.getElementById("confirmPassword").value;

      if (!current) {
        markInvalid("currentPassword");
        return;
      }
      if (!newPass) {
        markInvalid("newPassword");
        return;
      }
      if (!confirm) {
        markInvalid("confirmPassword");
        return;
      }

      if (newPass !== confirm) {
        showError(
          "Passwords do not match",
          "Please make sure both new passwords are identical."
        );
        return;
      }

      const score = scoreProfilePassword(newPass);
      if (score < 4) {
        showError(
          "Password is too weak",
          "Your new password must meet at least 4 of the 5 strength requirements."
        );
        return;
      }

      const fd = new FormData(this);
      fd.append("action", "update_password");

      showLoading("Updating password...", "Saving your new password.");

      try {
        const res = await fetch("/api/profile", { method: "POST", body: fd });
        const data = await res.json();

        await new Promise((r) => setTimeout(r, 500));
        hideLoading();

        if (data.success) {
          document.getElementById("successTitle").textContent =
            "Password Updated";
          document.getElementById("successMessage").textContent =
            data.message || "Your password has been changed.";
          showModal("successModal", "successCard");
          if (typeof initIcons === "function") initIcons();
          this.reset();

          // Reset the strength UI
          document.getElementById("pwStrengthWrap")?.classList.add("hidden");
          document.getElementById("pwMatchHint")?.classList.add("hidden");

          // Reset checklist
          document.querySelectorAll("#pwRequirements .pw-req").forEach((li) => {
            li.classList.remove("is-ok");
          });

          // Reset bars
          document.querySelectorAll(".pw-strength-bar").forEach((bar) => {
            bar.className =
              "pw-strength-bar h-1 flex-1 rounded-full transition-all bg-gray-200";
          });
        } else if (data.error) {
          showError("Could not update password", data.error);
        }
      } catch (err) {
        hideLoading();
        console.error(err);
        showError("Connection error", "Unable to reach the server.");
      }
    });
});

// Close success modal and refresh
function closeSuccessModal() {
  hideModal("successModal", "successCard");
  setTimeout(() => location.reload(), 200);
}