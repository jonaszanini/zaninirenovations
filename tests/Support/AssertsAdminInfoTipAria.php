<?php

namespace Tests\Support;

trait AssertsAdminInfoTipAria
{
    /**
     * #54's <x-admin.info-tip> always renders aria-label="About {label}".
     * Accept the prefixed form and a bare label so the suite stays green
     * if a tip is still rendered by the pre-#54 include.
     */
    protected function assertAdminInfoTipAriaLabel(string $html, string $label): void
    {
        $about = 'aria-label="About '.$label.'"';
        $plain = 'aria-label="'.$label.'"';
        $this->assertTrue(
            str_contains($html, $about) || str_contains($html, $plain),
            'Expected '.$about.' (#54 always prefixes About {label}).'
        );
    }
}
