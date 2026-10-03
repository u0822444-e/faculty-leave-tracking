<?php
/**
 * Reusable avatar renderer.
 *
 * Expected variables (set before include):
 *   $avatarSize   — e.g. 'w-7 h-7' (Tailwind classes)
 *   $avatarClass  — extra classes for the wrapper (optional)
 *   $avatarFile   — filename stored in DB, or null
 *   $avatarName   — full name (for initials fallback)
 *   $avatarTextSize — e.g. 'text-[10px]' (optional, defaults to text-xs)
 */
$avatarSize     = $avatarSize     ?? 'w-8 h-8';
$avatarClass    = $avatarClass    ?? '';
$avatarTextSize = $avatarTextSize ?? 'text-xs';

$initials = '';
if (!empty($avatarName)) {
    $parts = preg_split('/\s+/', trim($avatarName));
    $initials = strtoupper(
        substr($parts[0] ?? '', 0, 1) . substr(end($parts) ?: '', 0, 1)
    );
}
?>
<div class="<?= $avatarSize ?> rounded-full overflow-hidden bg-[#0F5E3D] text-white flex items-center justify-center font-semibold shrink-0 <?= $avatarClass ?>">
    <?php if (!empty($avatarFile)): ?>
        <img src="/uploads/avatars/<?= htmlspecialchars($avatarFile) ?>" alt="" class="w-full h-full object-cover">
    <?php else: ?>
        <span class="<?= $avatarTextSize ?>"><?= htmlspecialchars($initials) ?></span>
    <?php endif; ?>
</div>