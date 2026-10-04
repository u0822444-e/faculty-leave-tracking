// ============================================
// Reset Password page — logic
// ============================================

// ============================================
// Show / hide password
// ============================================
function togglePassword() {
    const checkbox = document.getElementById('showPassword');
    const type = checkbox.checked ? 'text' : 'password';
    document.getElementById('password').type = type;
    document.getElementById('confirm_password').type = type;
}

// ============================================
// Password rules
// ============================================
const RULES = {
    length:  v => v.length >= 8,
    upper:   v => /[A-Z]/.test(v),
    lower:   v => /[a-z]/.test(v),
    number:  v => /[0-9]/.test(v),
    special: v => /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(v),
};

function scorePassword(pw) {
    let score = 0;
    Object.values(RULES).forEach(fn => { if (fn(pw)) score++; });
    return score;
}

// ============================================
// Strength meter
// ============================================
function checkPasswordStrength() {
    const pw = document.getElementById('password').value;
    const wrap = document.getElementById('strengthWrap');
    const reqs = document.getElementById('pwRequirements');
    const bars = document.querySelectorAll('.strength-bar');
    const label = document.getElementById('strengthLabel');
    const hint = document.getElementById('strengthHint');

    // Hide everything if empty
    if (pw.length === 0) {
        wrap.classList.add('hidden');
        reqs.classList.add('hidden');
        checkMatch();
        return;
    }

    // Show strength meter once user starts typing
    wrap.classList.remove('hidden');

    // Update requirement checklist
    let passed = 0;
    document.querySelectorAll('#pwRequirements li').forEach(li => {
        const key = li.dataset.req;
        const dot = li.querySelector('.req-dot');
        if (RULES[key](pw)) {
            dot.className = 'req-dot w-1.5 h-1.5 rounded-full bg-[#0F5E3D]';
            li.classList.add('text-[#0F5E3D]');
            li.classList.remove('text-[#2C3E50]/60');
            passed++;
        } else {
            dot.className = 'req-dot w-1.5 h-1.5 rounded-full bg-gray-300';
            li.classList.remove('text-[#0F5E3D]');
            li.classList.add('text-[#2C3E50]/60');
        }
    });

    // Hide checklist once all rules pass, show otherwise
    if (passed === 5) {
        reqs.classList.add('hidden');
    } else {
        reqs.classList.remove('hidden');
    }

    // Score
    const score = scorePassword(pw);
    const levels = [
        { label: 'Very Weak',   color: 'bg-red-500',    hint: 'Add more variety' },
        { label: 'Very Weak',   color: 'bg-red-500',    hint: 'Add uppercase or numbers' },
        { label: 'Weak',        color: 'bg-orange-500', hint: 'Add numbers or symbols' },
        { label: 'Fair',        color: 'bg-yellow-500', hint: 'Almost there' },
        { label: 'Strong',      color: 'bg-[#0F5E3D]',  hint: 'Great password!' },
        { label: 'Very Strong', color: 'bg-[#0F5E3D]',  hint: 'Excellent!' },
    ];
    const info = levels[score];

    // Color bars
    bars.forEach((bar, i) => {
        bar.className = 'strength-bar h-1 flex-1 rounded-full transition-all ' +
            (i < score ? info.color : 'bg-gray-200');
    });

    label.textContent = 'Strength: ' + info.label;
    label.className = 'text-[11px] font-medium ' + (score >= 4 ? 'text-[#0F5E3D]' : 'text-gray-500');
    hint.textContent = info.hint;

    checkMatch();
}

// ============================================
// Confirm password match
// ============================================
function checkMatch() {
    const pw = document.getElementById('password').value;
    const confirm = document.getElementById('confirm_password').value;
    const hint = document.getElementById('matchHint');

    if (confirm.length === 0) {
        hint.classList.add('hidden');
        return;
    }

    hint.classList.remove('hidden');
    if (pw === confirm) {
        hint.textContent = '✓ Passwords match';
        hint.className = 'mt-2 text-[11px] text-[#0F5E3D]';
    } else {
        hint.textContent = '✕ Passwords do not match';
        hint.className = 'mt-2 text-[11px] text-red-600';
    }
}

// ============================================
// Block submit if weak or mismatched
// ============================================
function setupResetForm() {
    document.getElementById('resetForm')?.addEventListener('submit', function (e) {
        const pw = document.getElementById('password').value;
        const confirm = document.getElementById('confirm_password').value;
        const score = scorePassword(pw);

        if (score < 4) {
            e.preventDefault();
            document.getElementById('password').focus();
            return;
        }

        if (pw !== confirm) {
            e.preventDefault();
            document.getElementById('confirm_password').focus();
            return;
        }
    });
}

// ============================================
// Init
// ============================================
document.addEventListener('DOMContentLoaded', setupResetForm);