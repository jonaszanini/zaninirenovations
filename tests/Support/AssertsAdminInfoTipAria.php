<?php

namespace Tests\Support;

trait AssertsAdminInfoTipAria
{
    /**
     * #54's <x-admin.info-tip> always renders aria-label="About {label}".
     * This branch still uses the pre-#54 include (aria-label="{label}").
     * Accept both so the suite stays green now and after the #54 rebase.
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
