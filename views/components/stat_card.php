<?php
/**
 * Stat Card Component
 * 
 * @param string $label       - Card label (e.g., "Total Students")
 * @param string $value       - Main metric value (e.g., "1,598")
 * @param string $subtext     - Optional subtext below value
 * @param bool   $hasSparkline - Whether to show sparkline chart
 * @param array  $statusDots  - Optional array of status indicators
 */
?>

<div class="bg-white border border-[#E0E0E0] rounded-xl p-6 shadow-sm">
    <p class="text-sm font-medium text-[#2C3E50]/70 mb-1"><?= htmlspecialchars($label) ?></p>

    <div class="flex items-end justify-between">
        <span class="text-3xl font-bold text-[#2C3E50]"><?= htmlspecialchars($value) ?></span>

        <?php if (!empty($hasSparkline)): ?>
            <svg viewBox="0 0 100 30" class="w-24 h-8 text-[#0F5E3D]">
                <polyline 
                    points="0,25 15,20 30,22 45,15 60,18 75,10 90,12 100,5" 
                    fill="none" 
                    stroke="currentColor" 
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />
            </svg>
        <?php endif; ?>
    </div>

    <?php if (!empty($statusDots)): ?>
        <div class="mt-3 space-y-2">
            <?php foreach ($statusDots as $dot): ?>
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full <?= $dot['color'] ?>"></span>
                    <span class="text-sm <?= $dot['textColor'] ?? 'text-[#2C3E50]' ?>">
                        <?= htmlspecialchars($dot['label']) ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($subtext)): ?>
        <p class="text-xs text-[#2C3E50]/50 mt-2"><?= htmlspecialchars($subtext) ?></p>
    <?php endif; ?>
</div>