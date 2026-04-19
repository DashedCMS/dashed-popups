<?php

namespace Dashed\DashedPopups\Analytics;

class DeviceDetector
{
    public function detect(?string $userAgent): ?string
    {
        $ua = trim((string) $userAgent);
        if ($ua === '') {
            return null;
        }

        if (preg_match('/ipad|tablet|playbook|silk|kindle/i', $ua)) {
            return 'tablet';
        }

        if (preg_match('/mobile|iphone|ipod|android.*mobile|windows phone|blackberry|bb10|opera mini/i', $ua)) {
            return 'mobile';
        }

        return 'desktop';
    }
}
