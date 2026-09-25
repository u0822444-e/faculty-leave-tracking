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
    const pw = document.getElementById('newPassword').value;
    const wrap = document.getElementById('pwStrengthWrap');
    const bars = document.querySelectorAll('.pw-strength-bar');
    const label = document.getElementById('pwStrengthLabel');
    const hint = document.getElementById('pwStrengthHint');

    if (!wrap) return;

    if (pw.length === 0) {
        wrap.classList.add('hidden');
        // Reset checklist to grey
        document.querySelectorAll('#pwRequirements li').forEach(li => {
            const dot = li.querySelector('.req-dot');
            dot.className = 'req-dot w-1.5 h-1.5 rounded-full bg-gray-300';
            li.classList.remove('text-[#0F5E3D]');
            li.classList.add('text-[#2C3E50]/60');
        });
        checkProfilePasswordMatch();
        return;
    }

    wrap.classList.remove('hidden');

    // Update checklist (always visible, just changes colors)
    document.querySelectorAll('#pwRequirements li').forEach(li => {
        const key = li.dataset.req;
        const dot = li.querySelector('.req-dot');
        if (PROFILE_RULES[key](pw)) {
            dot.className = 'req-dot w-1.5 h-1.5 rounded-full bg-[#0F5E3D]';
            li.classList.add('text-[#0F5E3D]');
            li.classList.remove('text-[#2C3E50]/60');
        } else {
            dot.className = 'req-dot w-1.5 h-1.5 rounded-full bg-gray-300';
            li.classList.remove('text-[#0F5E3D]');
            li.classList.add('text-[#2C3E50]/60');
        }
    });

    // Score display
    const score = scoreProfilePassword(pw);
    const levels = [
        { label: 'Very Weak',   color: 'bg-red-500',    hint: 'Add more variety' },
        { label: 'Very Weak',   color: 'bg-red-500',    hint: 'Add uppercase or numbers' },
        { label: 'Weak',        color: 'bg-orange-500', hint: 'Add numbers or symbols' },
        { label: 'Fair',        color: 'bg-yellow-500', hint: 'Almost there' },
        { label: 'Strong',      color: 'bg-[#0F5E3D]',  hint: 'Great password!' },
        { label: 'Very Strong', color: 'bg-[#0F5E3D]',  hint: 'Excellent!' },
    ];
    const info = levels[score];

    bars.forEach((bar, i) => {
        bar.className = 'pw-strength-bar h-1 flex-1 rounded-full transition-all ' +
            (i < score ? info.color : 'bg-gray-200');
    });

    label.textContent = 'Strength: ' + info.label;
    label.className = 'text-[11px] font-medium ' + (score >= 4 ? 'text-[#0F5E3D]' : 'text-gray-500');
    hint.textContent = info.hint;

    checkProfilePasswordMatch();
}

function checkProfilePasswordMatch() {
  const pw = document.getElementById("newPassword").value;
  const confirm = document.getElementById("confirmPassword").value;
  const hint = document.getElementById("pwMatchHint");

  if (!hint) return;

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
      "profile-tab flex-1 sm:flex-none px-5 py-3 text-sm font-medium text-[#2C3E50]/60 border-b-2 border-transparent hover:text-[#2C3E50] transition";
  });
  const activeBtn = document.getElementById("tab" + capitalize(tab));
  if (activeBtn) {
    activeBtn.className =
      "profile-tab flex-1 sm:flex-none px-5 py-3 text-sm font-medium text-[#0F5E3D] border-b-2 border-[#0F5E3D] transition";
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
      document.addEventListener("click", closeProfileOnOutside, { once: true });
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
    document.addEventListener("click", closeProfileOnOutside, { once: true });
  }
}

// ============================================
// Form submissions
// ============================================
document.addEventListener("DOMContentLoaded", () => {
  if (typeof initIcons === "function") initIcons();

  // Info form
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

  // Password form
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
          document.getElementById("pwRequirements")?.classList.add("hidden");
          document.getElementById("pwMatchHint")?.classList.add("hidden");
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
