<?php
/**
 * Activity Item Component
 * 
 * @param string $icon      - Lucide icon name (e.g., "clock", "bell", "list")
 * @param string $title     - Activity title
 * @param string $timestamp - Activity timestamp
 */
?>

<div class="flex items-start gap-4 pb-4 border-b border-[#E0E0E0] last:border-0 last:pb-0">
    <div class="w-9 h-9 rounded-full bg-[#F1FDF6] flex items-center justify-center text-[#0F5E3D] shrink-0">
        <i data-lucide="<?= htmlspecialchars($icon) ?>" class="w-4 h-4"></i>
    </div>
    <div>
        <p class="text-sm font-medium text-[#2C3E50]"><?= htmlspecialchars($title) ?></p>
        <p class="text-xs text-[#2C3E50]/50"><?= htmlspecialchars($timestamp) ?></p>
    </div>
</div>