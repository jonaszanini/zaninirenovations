@extends('site.layouts.public')

@section('page_title', $page['title'])
@section('meta_description', $page['meta_description'])
@section('canonical', url('/reviews'))

@push('jsonld')
<script type="application/ld+json">{!! \App\Support\SeoStructuredData::toJsonLd(\App\Support\SeoStructuredData::reviewsPage($page)) !!}</script>
@endpush

@push('styles')
  <link rel="stylesheet" href="{{ asset('css/service-areas.css') }}?v={{ @filemtime(public_path('css/service-areas.css')) ?: time() }}">
  <link rel="stylesheet" href="{{ asset('css/public-pages.css') }}?v={{ @filemtime(public_path('css/public-pages.css')) ?: time() }}">
@endpush

@section('content')
  @php
    $google = \App\Support\GoogleReviews::profileUrl();
  @endphp
  <main class="sa-page pp-page" id="reviews">
    <header class="sa-hero pp-hero">
      <div class="container pp-wrap">
        <nav class="sa-breadcrumb" aria-label="Breadcrumb">
          <a href="{{ url('/') }}">Home</a>
          <span aria-hidden="true">/</span>
          <span>Reviews</span>
        </nav>
        <h1>{{ $page['h1'] }}</h1>
        <p>A lot of our work comes from referrals and from people reading what past clients have said. Here's a selection of real Google reviews, quoted word for word (some are shortened, marked with "...").</p>
        <p><strong>5.0 on Google from 26 reviews</strong> (as of October 2026). <a href="{{ $google }}" rel="noopener noreferrer" target="_blank">Read all reviews on Google</a></p>
      </div>
    </header>

    <section class="sa-section pp-section">
      <div class="container pp-wrap">
        <h2>Decks and outdoor projects</h2>
        <div class="pp-quote-grid">
          <blockquote class="pp-quote">
            <p>“We are extremely happy with the deck that Jonas built for us! From start to finish, he was incredibly detail-oriented, professional, and reliable. The work was completed on time, and the quality was excellent. ... We highly recommend him to anyone looking for someone who takes pride in their work, pays attention to the details, and delivers what they promise. We would definitely hire him again!”</p>
            <p class="pp-quote-cite">Beatriz C., Google <span class="pp-tag">Deck</span></p>
          </blockquote>
          <blockquote class="pp-quote">
            <p>“My deck railing looks great! Thank you Zanini!”</p>
            <p class="pp-quote-cite">Luccas C., Google <span class="pp-tag">Deck railing</span></p>
          </blockquote>
          <blockquote class="pp-quote">
            <p>“Jonas was super flexible and attentive, despite the many rains this summer we were able to finish the backyard renovation on a good time!”</p>
            <p class="pp-quote-cite">Renato B., Google <span class="pp-tag">Backyard</span></p>
          </blockquote>
          <blockquote class="pp-quote">
            <p>“The highlight of the project was the custom tunnel connecting our house door to the catio. He created a safe and beautiful solution that allows our cats to access the outdoor area without any risk of escaping. ... Jonas was always punctual, respectful, and easy to communicate with throughout the project.”</p>
            <p class="pp-quote-cite">Gabriel d'O., Google <span class="pp-tag">Catio</span></p>
          </blockquote>
        </div>

        <h2>Fences</h2>
        <div class="pp-quote-grid">
          <blockquote class="pp-quote">
            <p>“Huge thank you to Zanini Renovations for doing an amazing job on our new fence and our neighbours! They were fast, friendly, and left our yard spotless. The quality of the materials and workmanship is top-notch. Highly recommend to anyone in the area looking for great service at a fair price!”</p>
            <p class="pp-quote-cite">Tina W., Google <span class="pp-tag">Fence</span></p>
          </blockquote>
          <blockquote class="pp-quote">
            <p>“We recently had our fence installed by Zanini Renovations and they exceeded our expectations. The entire process was seamless, communication was excellent, and the quality of the work speaks for itself.”</p>
            <p class="pp-quote-cite">Donald W., Google <span class="pp-tag">Fence</span></p>
          </blockquote>
          <blockquote class="pp-quote">
            <p>“Jonas, it's amazing to work with, he did a beautiful fence in our backyard for our dogs, amongst other projects in the house. Highly recommend his work, professional, prompt, and gets the job complete on a quick timeframe no loose ends.”</p>
            <p class="pp-quote-cite">Mike D., Google <span class="pp-tag">Fence</span></p>
          </blockquote>
        </div>

        <h2>Basements, flooring and stairs</h2>
        <div class="pp-quote-grid">
          <blockquote class="pp-quote">
            <p>“I highly recommend Jonas! He was very professional, friendly and did a great job dry walling and installing flooring and trim in our basement. Communication was great and price was fair as well!”</p>
            <p class="pp-quote-cite">Alana H., Google <span class="pp-tag">Basement</span></p>
          </blockquote>
          <blockquote class="pp-quote">
            <p>“Jonas from Zanini Renovations did an excellent job on our flooring project. He was professional, easy to work with, and delivered outstanding workmanship with great attention to detail, even though the job was relatively small.”</p>
            <p class="pp-quote-cite">Robert W., Google <span class="pp-tag">Flooring</span></p>
          </blockquote>
          <blockquote class="pp-quote">
            <p>“The installation of the flooring on the second floor of my house was excellent, and the stairs were finished beautifully. The quality of the workmanship exceeded my expectations, and everything was completed with great care and precision.”</p>
            <p class="pp-quote-cite">Antonio S., Google <span class="pp-tag">Flooring and stairs</span></p>
          </blockquote>
        </div>

        <h2>Kitchens, built-ins and custom work</h2>
        <div class="pp-quote-grid">
          <blockquote class="pp-quote">
            <p>“Jonas Zanini was the best contractor I've ever had in Canada. He truly pays attention to details that make a real difference when it comes to finishing work. We renovated our entire house with him — he built our basement from scratch, and the drywall finishing was fantastic, always thinking about how to make the best use of every space. ... He is careful, clean, and his prices are very reasonable considering that he also creates and suggests great design ideas. I highly recommend him.”</p>
            <p class="pp-quote-cite">Leo B., Google <span class="pp-tag">Whole-house renovation</span></p>
          </blockquote>
          <blockquote class="pp-quote">
            <p>“Jonas responded to my enquires and came to site really quickly, delivering an excellent service for a custom kitchen renovation. Medium project, but one that would have taken me a lot longer!. Thanks Jonas!. Highly recommended.”</p>
            <p class="pp-quote-cite">Marcelo S., Google <span class="pp-tag">Kitchen</span></p>
          </blockquote>
          <blockquote class="pp-quote">
            <p>“I had a great experience with Jonas. They did an excellent job at my kitchen and living room, very professional and all was delivered on time. I recommend his business.”</p>
            <p class="pp-quote-cite">Douglas O.S., Google <span class="pp-tag">Kitchen and living room</span></p>
          </blockquote>
          <blockquote class="pp-quote">
            <p>“Job well done, easy to work with and was able to do a custom design from an inspiration picture. Very reasonable and made sure I was happy with the result. Very trustworthy and reliable. Highly recommend!”</p>
            <p class="pp-quote-cite">Jennifer A., Google <span class="pp-tag">Custom design</span></p>
          </blockquote>
          <blockquote class="pp-quote">
            <p>“Jonas Zanini built a custom office desk at my workplace, and the quality and attention to detail were excellent. Professional, reliable, and great craftsmanship.”</p>
            <p class="pp-quote-cite">Kelly C.F., Google <span class="pp-tag">Commercial: office desk</span></p>
          </blockquote>
        </div>

        <h2>Working with Jonas</h2>
        <div class="pp-quote-grid">
          <blockquote class="pp-quote">
            <p>“Jonas not only delivered what I asked but it was even better than expected. On time and within budget! Cannot recommend anyone better looking for renovations inside and outside!”</p>
            <p class="pp-quote-cite">Pamela W., Google</p>
          </blockquote>
          <blockquote class="pp-quote">
            <p>“Jonas is absolutely delightful to work with. We have hired him for two jobs; his work was thoughtful, thorough, and timely. Very friendly and professional.”</p>
            <p class="pp-quote-cite">Randi H., Google</p>
          </blockquote>
          <blockquote class="pp-quote">
            <p>“We especially appreciated how organized and clean he was throughout the entire process. We are happy with the results and will definitely work with him again in the future.”</p>
            <p class="pp-quote-cite">Kawana B., Google</p>
          </blockquote>
          <blockquote class="pp-quote">
            <p>“Jonas is an excellent professional. We're very satisfied with his work. He not only did what we ask but also gave us great ideas. 100% recommend!!”</p>
            <p class="pp-quote-cite">Diana S., Google</p>
          </blockquote>
        </div>
      </div>
    </section>

    <section class="sa-section pp-section">
      <div class="container pp-wrap">
        <h2>Leave a review</h2>
        <p>Worked with us? A short review helps other homeowners find a local carpenter they can trust, and we read every one.</p>
        <div class="pp-cta-row">
          <a class="btn btn-theme sa-btn" href="{{ config('seo.google_review_url') }}" rel="noopener noreferrer" target="_blank">Leave a Google review</a>
          <a class="btn btn-theme sa-btn sa-btn--ghost" href="https://www.bbb.org/ca/on/carleton-place/profile/general-contractor/zanini-renovations-0117-257297/leave-a-review" rel="noopener noreferrer" target="_blank">Zanini Renovations on BBB</a>
          <a class="btn btn-theme sa-btn sa-btn--ghost" href="https://trustedpros.ca/company/zanini-renovations" rel="noopener noreferrer" target="_blank">Review Zanini Renovations on TrustedPros</a>
          <a class="btn btn-theme sa-btn sa-btn--ghost" href="https://www.yelp.com/biz/zanini-renovations-carleton-place" rel="noopener noreferrer" target="_blank">Review Zanini Renovations on Yelp</a>
        </div>

        <h2>Find Zanini Renovations online</h2>
        <ul class="pp-links">
          <li>Google: <a href="{{ $google }}" rel="noopener noreferrer" target="_blank">{{ $google }}</a> (also <a href="https://maps.google.com/?cid=7676669856408637146" rel="noopener noreferrer" target="_blank">https://maps.google.com/?cid=7676669856408637146</a>)</li>
          <li>BBB: <a href="https://www.bbb.org/ca/on/carleton-place/profile/general-contractor/zanini-renovations-0117-257297" rel="noopener noreferrer" target="_blank">https://www.bbb.org/ca/on/carleton-place/profile/general-contractor/zanini-renovations-0117-257297</a></li>
          <li>TrustedPros: <a href="https://trustedpros.ca/company/zanini-renovations" rel="noopener noreferrer" target="_blank">https://trustedpros.ca/company/zanini-renovations</a></li>
          <li>Yelp: <a href="https://www.yelp.com/biz/zanini-renovations-carleton-place" rel="noopener noreferrer" target="_blank">https://www.yelp.com/biz/zanini-renovations-carleton-place</a></li>
          <li>Facebook: <a href="https://www.facebook.com/zaninirenovations" rel="noopener noreferrer" target="_blank">https://www.facebook.com/zaninirenovations</a></li>
          <li>Instagram: <a href="https://www.instagram.com/zaninirenovations/" rel="noopener noreferrer" target="_blank">https://www.instagram.com/zaninirenovations/</a></li>
        </ul>

        <h2>How we handle feedback</h2>
        <p>If you have a question or a concern during or after a job, call or email us and we'll talk it through. Details of our workmanship warranty are on the <a href="{{ url('/warranty') }}">warranty page</a>.</p>
      </div>
    </section>

    <div class="container pp-wrap pp-cta-bottom">
      @include('site.partials.public-page-cta', ['cta' => $cta])
    </div>
  </main>
@endsection
