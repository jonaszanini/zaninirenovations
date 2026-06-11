{{-- Numeric HH:MM entry — wired by admin-client-projects.js (data-cp-time-start / data-cp-time-end). --}}
<input type="text"
       name="{{ $name }}"
       class="admin-input cp-time-input"
       inputmode="numeric"
       autocomplete="off"
       placeholder="HH:MM"
       maxlength="5"
       data-cp-time-{{ $role }}
       value="{{ $value ?? '' }}">
