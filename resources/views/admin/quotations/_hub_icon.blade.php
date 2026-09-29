{{-- Quotation hub icon. One color (currentColor) so it follows light and dark themes. --}}
@switch($name)
  @case('standard')
    <svg class="fq-hub-card-svg" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
      <path d="M16 8h20.5L48 19.5V52a4 4 0 0 1-4 4H16a4 4 0 0 1-4-4V12a4 4 0 0 1 4-4z" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linejoin="round"/>
      <path d="M36 8v12h12" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linejoin="round"/>
      <rect x="18" y="28" width="6" height="6" rx="1.2" fill="none" stroke="currentColor" stroke-width="2.2"/>
      <rect x="18" y="38" width="6" height="6" rx="1.2" fill="none" stroke="currentColor" stroke-width="2.2"/>
      <path d="M28 31h12M28 41h8" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
      <path d="M36 46.5 41 51.5 52 38" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
    @break

  @case('fence')
    <svg class="fq-hub-card-svg" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
      <path fill="currentColor" d="M8 13.2 14 6l6 7.2V52H8zm14 0L28 6l6 7.2V52H22zm14 0L42 6l6 7.2V52H36zm14 0L56 6l6 7.2V52H50z"/>
      <path fill="currentColor" d="M4 18h56v6.5H4zm0 16h56v6.5H4z"/>
    </svg>
    @break

  @case('paving')
    <svg class="fq-hub-card-svg" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
      <defs>
        <clipPath id="fq-hub-pave-clip">
          <rect x="7" y="7" width="50" height="50" rx="4"/>
        </clipPath>
      </defs>
      <g clip-path="url(#fq-hub-pave-clip)" fill="currentColor">
        <rect x="7" y="7" width="23" height="13" rx="1.5"/>
        <rect x="34" y="7" width="23" height="13" rx="1.5"/>
        <rect x="-4" y="24" width="23" height="13" rx="1.5"/>
        <rect x="23" y="24" width="23" height="13" rx="1.5"/>
        <rect x="50" y="24" width="23" height="13" rx="1.5"/>
        <rect x="7" y="41" width="23" height="16" rx="1.5"/>
        <rect x="34" y="41" width="23" height="16" rx="1.5"/>
      </g>
    </svg>
    @break

  @case('flooring')
    <svg class="fq-hub-card-svg" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
      <g fill="currentColor">
        <rect x="8" y="8" width="22" height="10" rx="1.2"/>
        <rect x="33" y="8" width="23" height="10" rx="1.2"/>
        <rect x="8" y="21" width="32" height="10" rx="1.2"/>
        <rect x="43" y="21" width="13" height="10" rx="1.2"/>
        <rect x="8" y="34" width="14" height="10" rx="1.2"/>
        <rect x="25" y="34" width="31" height="10" rx="1.2"/>
        <rect x="8" y="47" width="28" height="10" rx="1.2"/>
        <rect x="39" y="47" width="17" height="10" rx="1.2"/>
      </g>
    </svg>
    @break

  @case('tile')
    <svg class="fq-hub-card-svg" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
      <g fill="currentColor">
        <rect x="8" y="8" width="16" height="16" rx="2"/>
        <rect x="40" y="8" width="16" height="16" rx="2"/>
        <rect x="24" y="24" width="16" height="16" rx="2"/>
        <rect x="8" y="40" width="16" height="16" rx="2"/>
        <rect x="40" y="40" width="16" height="16" rx="2"/>
      </g>
    </svg>
    @break

  @case('painting')
    <svg class="fq-hub-card-svg" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
      <path d="M18 16c0-7 28-7 28 0l-4 12H22z" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linejoin="round"/>
      <rect x="21" y="28" width="22" height="8" rx="1.2" fill="currentColor"/>
      <path d="M27 36h10l-1.4 16.5a3.6 3.6 0 0 1-7.2 0z" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linejoin="round"/>
    </svg>
    @break

  @case('stairs')
    <svg class="fq-hub-card-svg" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
      <path d="M8 30 34 10h20" fill="none" stroke="currentColor" stroke-width="2.7" stroke-linecap="round" stroke-linejoin="round"/>
      <path d="M34 10v9" fill="none" stroke="currentColor" stroke-width="2.7" stroke-linecap="round"/>
      <path d="M10 54V44h12V34h12V24h12V54" fill="none" stroke="currentColor" stroke-width="2.7" stroke-linejoin="round"/>
      <path d="M10 54h42" fill="none" stroke="currentColor" stroke-width="2.7" stroke-linecap="round"/>
    </svg>
    @break

  @case('low-deck')
    <svg class="fq-hub-card-svg" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
      <path d="M10 28 32 16 54 28 32 40Z" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linejoin="round"/>
      <path d="M17.5 24 39.5 36M24.5 20 46.5 32" fill="none" stroke="currentColor" stroke-width="2.2"/>
      <path d="M10 28v6l22 12 22-12v-6M32 40v12" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linejoin="round"/>
      <path d="M18 38v7M46 38v7" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
    </svg>
    @break

  @case('basement')
    <svg class="fq-hub-card-svg" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
      <rect x="10" y="6" width="44" height="52" rx="2" fill="none" stroke="currentColor" stroke-width="2.4"/>
      <path d="M10 34h44" stroke="currentColor" stroke-width="2.2"/>
      <rect x="16" y="13" width="13" height="12" rx="1.2" fill="none" stroke="currentColor" stroke-width="2.2"/>
      <rect x="35" y="13" width="13" height="12" rx="1.2" fill="none" stroke="currentColor" stroke-width="2.2"/>
      <path d="M22.5 13v12M16 19h13M41.5 13v12M35 19h13" stroke="currentColor" stroke-width="1.4"/>
      <rect x="16" y="40" width="9" height="18" rx="1" fill="none" stroke="currentColor" stroke-width="2.2"/>
      <path d="M30 43h18M30 49h18" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>
    </svg>
    @break

  @case('trimming')
    <svg class="fq-hub-card-svg" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
      <rect x="12" y="10" width="40" height="44" rx="1" fill="none" stroke="currentColor" stroke-width="2.6"/>
      <rect x="22" y="20" width="20" height="24" rx="1" fill="none" stroke="currentColor" stroke-width="2.6"/>
      <path d="M12 10 22 20M52 10 42 20M12 54 22 44M52 54 42 44" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"/>
    </svg>
    @break

  @case('shed')
    <svg class="fq-hub-card-svg" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
      <path d="M8 30 32 10l24 20" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linejoin="round"/>
      <rect x="14" y="30" width="36" height="24" fill="none" stroke="currentColor" stroke-width="2.5"/>
      <rect x="28" y="38" width="10" height="16" rx="1" fill="none" stroke="currentColor" stroke-width="2.2"/>
      <rect x="18" y="36" width="8" height="8" rx="1" fill="none" stroke="currentColor" stroke-width="2"/>
      <path d="M22 36v8M18 40h8" stroke="currentColor" stroke-width="1.4"/>
    </svg>
    @break

  @case('millwork')
    <svg class="fq-hub-card-svg" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
      <rect x="8" y="8" width="48" height="48" rx="2" fill="none" stroke="currentColor" stroke-width="2.4"/>
      <g fill="none" stroke="currentColor" stroke-width="2.2">
        <rect x="14" y="14" width="16" height="16" rx="1"/>
        <rect x="34" y="14" width="16" height="16" rx="1"/>
        <rect x="14" y="34" width="16" height="16" rx="1"/>
        <rect x="34" y="34" width="16" height="16" rx="1"/>
      </g>
    </svg>
    @break
@endswitch
