@php
  $formClass = $formClass ?? 'contact-form-block';
@endphp
<div class="{{ $formClass }}">
  <form role="form" action="#" method="post" id="contact-form">
    <div class="form-group">
      <label for="user_name" class="sr-only">Your name</label>
      <input type="text" class="form-control" id="user_name" name="user_name" placeholder="Your name" required autocomplete="name" data-msg="Please enter your name">
    </div>

    <div class="form-group">
      <label for="user_email" class="sr-only">Email address</label>
      <input type="email" class="form-control" id="user_email" name="user_email" placeholder="Email address" required autocomplete="email" data-msg="Please enter a valid email">
    </div>

    <div class="form-group">
      <label class="home-contact-date-label" for="user_phone">Phone number <span class="text-muted">(optional)</span></label>
      <input type="tel" class="form-control" id="user_phone" name="user_phone" placeholder="e.g. (613) 555-1234" maxlength="30" autocomplete="tel">
    </div>

    <div class="form-group home-contact-sms-opt-in">
      <label class="home-contact-sms-opt-in__label" for="sms_opt_in">
        <input type="checkbox" class="home-contact-sms-opt-in__input" id="sms_opt_in" name="sms_opt_in" value="1">
        <span class="home-contact-sms-opt-in__text">
          By checking this box, I agree to receive service notifications via SMS from Zanini Renovations, including project alerts, schedule updates, and quotation notifications. Message frequency varies. Message and data rates may apply. Reply STOP to opt out. See our
          <a href="{{ route('terms-and-conditions') }}" target="_blank" rel="noopener noreferrer">Terms of Service</a>
          and
          <a href="{{ route('privacy-policy') }}" target="_blank" rel="noopener noreferrer">Privacy Policy</a>.
        </span>
      </label>
    </div>

    <div class="form-group">
      <label for="location_city" class="sr-only">Location or city</label>
      <input type="text" class="form-control" id="location_city" name="location_city" placeholder="Location / city" required autocomplete="address-level2" data-msg="Please enter your location or city">
    </div>

    <div class="form-group">
      <label class="home-contact-date-label" for="starting_date">Starting date <span class="text-muted">(optional)</span></label>
      <input type="date" class="form-control home-contact-date-input" id="starting_date" name="starting_date">
    </div>

    <div class="form-group">
      <label class="home-contact-date-label" for="budget">What is your budget? <span class="text-muted">(optional)</span></label>
      <input type="text" class="form-control" id="budget" name="budget" placeholder="e.g. $10,000–$25,000 or a ballpark amount" maxlength="500" autocomplete="off">
    </div>

    <div class="form-group">
      <label for="email_subject" class="sr-only">Subject</label>
      <input type="text" class="form-control" id="email_subject" name="email_subject" placeholder="Subject" required data-msg="Please enter a subject">
    </div>

    <div class="form-group">
      <label for="email_message" class="sr-only">Message</label>
      <textarea class="form-control" rows="5" name="email_message" id="email_message" placeholder="Describe your project, timeline, and what you have in mind" required data-msg="Please enter your message"></textarea>
    </div>

    <div class="form-group">
      <button type="submit" class="btn btn-theme btn-custom sa-btn">Send now</button>
    </div>

    <div id="contact-form-feedback" class="home-contact-form-feedback" role="status" aria-live="polite"></div>
  </form>
</div>
