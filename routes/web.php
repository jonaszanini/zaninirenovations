<?php

use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\MaterialController;
use App\Http\Controllers\Admin\ClientProjectInvoiceController;
use App\Http\Controllers\Admin\ClientProjectQuotationController;
use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\Auth\PasswordResetController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\GooglePhotosController;
use App\Http\Controllers\Admin\PavingDatabaseController;
use App\Http\Controllers\Admin\PavingQuotationController;
use App\Http\Controllers\Admin\StairsDatabaseController;
use App\Http\Controllers\Admin\StairsQuotationController;
use App\Http\Controllers\Admin\ProjectController;
use App\Http\Controllers\ContactFormController;
use App\Models\BlogPost;
use App\Models\Project;
use Illuminate\Support\Facades\Route;

Route::post('/contact-email', [ContactFormController::class, 'submit'])
    ->middleware('throttle:8,1')
    ->name('contact.submit');

/**
 * Production HTTP cron trigger (cron-job.org). Protected by CRON_HTTP_TOKEN
 * (404s when the token is missing or wrong). Schedule once daily at 9am
 * Eastern — each hit runs all daily automations via automations:run-daily.
 */
Route::get('/cron/run', [\App\Http\Controllers\CronController::class, 'run'])
    ->middleware('throttle:12,1')
    ->name('cron.run');

Route::view('/warranty', 'site.warranty')->name('warranty');
Route::view('/privacy-policy', 'site.privacy-policy')->name('privacy-policy');
Route::view('/terms-and-conditions', 'site.terms-and-conditions')->name('terms-and-conditions');

Route::permanentRedirect('/services/custom-kitchen-remodel', '/');

Route::get('/sitemap.xml', function () {
    $urls = \App\Support\PublicSitemap::urls();

    return response()
        ->view('site.sitemap-xml', compact('urls'), 200)
        ->header('Content-Type', 'application/xml; charset=UTF-8');
})->name('sitemap');

Route::get('/robots.txt', function () {
    return response(\App\Support\RobotsTxtGenerator::generate(), 200)
        ->header('Content-Type', 'text/plain; charset=UTF-8');
})->name('robots');

Route::get('/llms.txt', function () {
    return response(\App\Support\LlmsTxtGenerator::generate(), 200)
        ->header('Content-Type', 'text/plain; charset=UTF-8');
})->name('llms-txt');

/** Signed link (no login) so clients can open invoice PDFs from SMS — see ClientProjectInvoiceController::sendInvoiceSms. */
Route::get('/client/invoice/{client_project}/{invoice}', [ClientProjectInvoiceController::class, 'guestDownloadInvoicePdf'])
    ->middleware('signed')
    ->name('public.client-project.invoice-pdf');
Route::get('/client/receipt/{client_project}/{invoice}', [ClientProjectInvoiceController::class, 'guestDownloadReceiptPdf'])
    ->middleware('signed')
    ->name('public.client-project.receipt-pdf');
Route::get('/client/quotation/{client_project}/{quotation}', [ClientProjectQuotationController::class, 'guestDownloadQuotationPdf'])
    ->middleware('signed')
    ->name('public.client-project.quotation-pdf');
Route::get('/helper/payment/{helper_payment}/acknowledge', [\App\Http\Controllers\Admin\ClientProjectHelperController::class, 'acknowledgeHelperPayment'])
    ->middleware('signed')
    ->name('public.helper-payment.acknowledge');

Route::get('/', function () {
    $projects = Project::query()
        ->with([
            'photos' => fn ($q) => $q->orderBy('sort_order'),
            'categories',
        ])
        ->orderByCompletionLatest()
        ->get();

    $projectFilterCategories = $projects
        ->flatMap(fn (Project $p) => $p->categories)
        ->unique('id')
        ->sortBy(fn ($c) => [$c->sort_order, strtolower($c->name)])
        ->values();

    $homeBlogPosts = BlogPost::query()
        ->published()
        ->with(['categories:id,name,slug'])
        ->orderByDesc('published_at')
        ->orderByDesc('id')
        ->limit(4)
        ->get();

    $homeBlogFeatured = $homeBlogPosts->first();
    $homeBlogMore = $homeBlogPosts->slice(1, 3)->values();

    return view('site.home', compact(
        'projects',
        'projectFilterCategories',
        'homeBlogFeatured',
        'homeBlogMore',
    ));
});

Route::get('/blog', [\App\Http\Controllers\BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/feed.xml', [\App\Http\Controllers\BlogRssController::class, 'index'])->name('blog.rss');
Route::post('/blog/{post:slug}/comments', [\App\Http\Controllers\BlogCommentController::class, 'store'])
    ->middleware('throttle:6,1')
    ->name('blog.comments.store');
Route::get('/blog/{post:slug}', [\App\Http\Controllers\BlogController::class, 'show'])->name('blog.show');

Route::middleware('guest')->group(function () {
    Route::get('/admin/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/admin/login', [LoginController::class, 'login']);

    // Password recovery / reset.
    Route::get('/admin/forgot-password', [PasswordResetController::class, 'showLinkRequestForm'])
        ->name('password.request');
    Route::post('/admin/forgot-password', [PasswordResetController::class, 'sendResetLinkEmail'])
        ->middleware('throttle:6,1')
        ->name('password.email');
    Route::get('/admin/reset-password/{token}', [PasswordResetController::class, 'showResetForm'])
        ->name('password.reset');
    Route::post('/admin/reset-password', [PasswordResetController::class, 'reset'])
        ->middleware('throttle:6,1')
        ->name('password.update');
});

Route::middleware('auth')->get('/dashboard', [DashboardController::class, 'index'])
    ->name('admin.dashboard');

Route::middleware(['auth', 'restrict.destructive'])->prefix('admin')->name('admin.')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/', function () {
        return redirect()->route('admin.dashboard');
    });

    Route::get('google-photos/connect', [GooglePhotosController::class, 'connect'])->name('google-photos.connect');
    Route::get('google-photos/callback', [GooglePhotosController::class, 'callback'])->name('google-photos.callback');
    Route::post('google-photos/disconnect', [GooglePhotosController::class, 'disconnect'])->name('google-photos.disconnect');
    Route::post('google-photos/picker-sessions', [GooglePhotosController::class, 'createPickerSession'])->name('google-photos.picker-sessions.create');
    Route::get('google-photos/picker-sessions/{sessionId}', [GooglePhotosController::class, 'showPickerSession'])->name('google-photos.picker-sessions.show');
    Route::get('google-photos/picker-sessions/{sessionId}/media', [GooglePhotosController::class, 'pickedMedia'])->name('google-photos.picker-sessions.media');
    Route::get('google-photos/thumbnail', [GooglePhotosController::class, 'thumbnail'])->name('google-photos.thumbnail');

    // Portfolio (a.k.a. "Latest Projects Photos") — manages Project/ProjectPhoto used on the public home page.
    Route::post('portfolio/ai-improve-photos', [ProjectController::class, 'aiImprovePhotos'])
        ->name('portfolio.ai-improve-photos');
    Route::post('portfolio/draft-photos', [ProjectController::class, 'stashDraftPhotos'])
        ->name('portfolio.draft-photos.store');
    Route::delete('portfolio/draft-photos', [ProjectController::class, 'removeDraftPhoto'])
        ->name('portfolio.draft-photos.destroy');
    Route::post('portfolio/ai-improve-description', [ProjectController::class, 'aiImproveDescription'])
        ->name('portfolio.ai-improve-description');
    Route::post('portfolio/{project}/ai-improve-description', [ProjectController::class, 'aiImproveDescriptionForProject'])
        ->name('portfolio.ai-improve-description.edit');
    Route::post('portfolio/{project}/photos/{photo}/rotate', [ProjectController::class, 'rotatePhoto'])
        ->name('portfolio.photos.rotate');
    Route::post('portfolio/{project}/photos/{photo}/crop', [ProjectController::class, 'cropPhoto'])
        ->name('portfolio.photos.crop');
    Route::resource('portfolio', ProjectController::class)
        ->parameters(['portfolio' => 'project'])
        ->except(['show']);
    Route::resource('categories', CategoryController::class)->except(['show']);

    // Client directory (shared contacts for new projects).
    Route::get('clients/search', [ClientController::class, 'search'])->name('clients.search');
    Route::resource('clients', ClientController::class)->except(['show']);
    Route::resource('client-tags', \App\Http\Controllers\Admin\ClientTagController::class)
        ->except(['show']);

    // Team / role management (admin vs staff — see RestrictDestructiveActions).
    Route::resource('users', \App\Http\Controllers\Admin\UserController::class)
        ->only(['index', 'edit', 'update']);

    // Global search — one box across clients, projects, and quotations.
    Route::get('search', [\App\Http\Controllers\Admin\GlobalSearchController::class, 'index'])
        ->name('search.index');

    // Task / reminder inbox.
    Route::patch('tasks/{task}/toggle', [\App\Http\Controllers\Admin\TaskController::class, 'toggle'])
        ->name('tasks.toggle');
    Route::resource('tasks', \App\Http\Controllers\Admin\TaskController::class)
        ->only(['index', 'store', 'update', 'destroy']);

    Route::get('materials/vendor-link/amazon/{asin}', [\App\Http\Controllers\Admin\MaterialVendorLinkController::class, 'amazon'])
        ->name('materials.vendor-link.amazon')
        ->where('asin', '[A-Za-z0-9]{10}');
    Route::get('materials/config', [MaterialController::class, 'config'])->name('materials.config');
    Route::post('materials/config/delete-failed', [MaterialController::class, 'destroyFailed'])
        ->name('materials.config.delete-failed');
    Route::post('materials/config/vendors', [\App\Http\Controllers\Admin\MaterialVendorController::class, 'store'])
        ->name('materials.config.vendors.store');
    Route::put('materials/config/vendors/{materialVendor}', [\App\Http\Controllers\Admin\MaterialVendorController::class, 'update'])
        ->name('materials.config.vendors.update');
    Route::delete('materials/config/vendors/{materialVendor}', [\App\Http\Controllers\Admin\MaterialVendorController::class, 'destroy'])
        ->name('materials.config.vendors.destroy');
    Route::post('materials/config/categories', [\App\Http\Controllers\Admin\MaterialCategoryController::class, 'store'])
        ->name('materials.config.categories.store');
    Route::put('materials/config/categories/{materialCategory}', [\App\Http\Controllers\Admin\MaterialCategoryController::class, 'update'])
        ->name('materials.config.categories.update');
    Route::delete('materials/config/categories/{materialCategory}', [\App\Http\Controllers\Admin\MaterialCategoryController::class, 'destroy'])
        ->name('materials.config.categories.destroy');
    Route::post('materials/bulk-destroy', [MaterialController::class, 'bulkDestroy'])
        ->name('materials.bulk-destroy');
    Route::post('materials/bulk-import/start', [MaterialController::class, 'bulkImportStart'])
        ->name('materials.bulk-import.start');
    Route::post('materials/bulk-import/step', [MaterialController::class, 'bulkImportStep'])
        ->name('materials.bulk-import.step');
    Route::post('materials/bulk-import', [MaterialController::class, 'bulkImport'])
        ->name('materials.bulk-import');
    Route::post('materials/sync-all/start', [MaterialController::class, 'syncAllStart'])
        ->name('materials.sync-all.start');
    Route::post('materials/sync-all/step', [MaterialController::class, 'syncAllStep'])
        ->name('materials.sync-all.step');
    Route::post('materials/config/ai-short-names/start', [MaterialController::class, 'aiShortNamesStart'])
        ->name('materials.config.ai-short-names.start');
    Route::post('materials/config/ai-short-names/step', [MaterialController::class, 'aiShortNamesStep'])
        ->name('materials.config.ai-short-names.step');
    Route::post('materials/{material}/sync-price', [MaterialController::class, 'syncPrice'])
        ->name('materials.sync-price');
    Route::resource('materials', MaterialController::class)->except(['show']);

    Route::post('blog/posts/upload-image', [\App\Http\Controllers\Admin\BlogPostController::class, 'uploadImage'])
        ->name('blog.posts.upload-image');
    Route::post('blog/posts/ai-metadata', [\App\Http\Controllers\Admin\BlogPostController::class, 'generateAiMetadata'])
        ->name('blog.posts.ai-metadata');
    Route::resource('blog/posts', \App\Http\Controllers\Admin\BlogPostController::class)
        ->parameters(['posts' => 'post'])
        ->except(['show'])
        ->names('blog.posts');
    Route::resource('blog/categories', \App\Http\Controllers\Admin\BlogCategoryController::class)
        ->parameters(['categories' => 'category'])
        ->except(['show'])
        ->names('blog.categories');
    Route::post('blog/tags/bulk', [\App\Http\Controllers\Admin\BlogTagController::class, 'bulkStore'])
        ->name('blog.tags.bulk');
    Route::resource('blog/tags', \App\Http\Controllers\Admin\BlogTagController::class)
        ->parameters(['tags' => 'tag'])
        ->except(['show'])
        ->names('blog.tags');
    Route::get('blog/comments', [\App\Http\Controllers\Admin\BlogCommentController::class, 'index'])
        ->name('blog.comments.index');
    Route::post('blog/comments/{comment}/approve', [\App\Http\Controllers\Admin\BlogCommentController::class, 'approve'])
        ->name('blog.comments.approve');
    Route::delete('blog/comments/{comment}', [\App\Http\Controllers\Admin\BlogCommentController::class, 'destroy'])
        ->name('blog.comments.destroy');

    // Client Projects management (quote → completion lifecycle).
    Route::get('client-projects/calendar', [\App\Http\Controllers\Admin\ClientProjectController::class, 'calendar'])
        ->name('client-projects.calendar');
    Route::get('client-projects/calendar/picker-events', [\App\Http\Controllers\Admin\ClientProjectController::class, 'schedulePickerEvents'])
        ->name('client-projects.calendar.picker-events');
    Route::get('client-projects/duplicate-check', [\App\Http\Controllers\Admin\ClientProjectController::class, 'duplicateCheck'])
        ->name('client-projects.duplicate-check');
    Route::post('calendar/personal-events', [\App\Http\Controllers\Admin\AdminCalendarEventController::class, 'store'])
        ->name('calendar.personal-events.store');
    Route::put('calendar/personal-events/{admin_calendar_event}', [\App\Http\Controllers\Admin\AdminCalendarEventController::class, 'update'])
        ->name('calendar.personal-events.update');
    Route::delete('calendar/personal-events/{admin_calendar_event}', [\App\Http\Controllers\Admin\AdminCalendarEventController::class, 'destroy'])
        ->name('calendar.personal-events.destroy');
    Route::post('client-projects/{client_project}/status', [\App\Http\Controllers\Admin\ClientProjectStatusController::class, 'update'])
        ->name('client-projects.status.update');
    Route::post('client-projects/{client_project}/reopen', [\App\Http\Controllers\Admin\ClientProjectStatusController::class, 'reopen'])
        ->name('client-projects.reopen');
    Route::post('client-projects/{client_project}/installments', [\App\Http\Controllers\Admin\ClientProjectFinanceController::class, 'saveInstallments'])
        ->name('client-projects.installments.save');
    Route::post('client-projects/{client_project}/installments/recalculate/preview', [\App\Http\Controllers\Admin\ClientProjectFinanceController::class, 'previewRecalculateInstallments'])
        ->name('client-projects.installments.recalculate-preview');
    Route::post('client-projects/{client_project}/installments/recalculate', [\App\Http\Controllers\Admin\ClientProjectFinanceController::class, 'recalculateInstallments'])
        ->name('client-projects.installments.recalculate');
    Route::post('client-projects/{client_project}/installments/{installment}/paid', [\App\Http\Controllers\Admin\ClientProjectFinanceController::class, 'togglePaid'])
        ->name('client-projects.installments.toggle-paid');
    Route::post('client-projects/{client_project}/expenses', [\App\Http\Controllers\Admin\ClientProjectFinanceController::class, 'storeExpense'])
        ->name('client-projects.expenses.store');
    Route::post('client-projects/{client_project}/expenses/send', [\App\Http\Controllers\Admin\ClientProjectFinanceController::class, 'sendExpensesToClient'])
        ->name('client-projects.expenses.send');
    Route::delete('client-projects/{client_project}/expenses/{expense}', [\App\Http\Controllers\Admin\ClientProjectFinanceController::class, 'destroyExpense'])
        ->name('client-projects.expenses.destroy');
    Route::put('client-projects/{client_project}/expenses/{expense}', [\App\Http\Controllers\Admin\ClientProjectFinanceController::class, 'updateExpense'])
        ->name('client-projects.expenses.update');
    Route::post('client-projects/{client_project}/returns', [\App\Http\Controllers\Admin\ClientProjectFinanceController::class, 'storeReturn'])
        ->name('client-projects.returns.store');
    Route::delete('client-projects/{client_project}/returns/{project_return}', [\App\Http\Controllers\Admin\ClientProjectFinanceController::class, 'destroyReturn'])
        ->name('client-projects.returns.destroy');
    Route::post('client-projects/{client_project}/time', [\App\Http\Controllers\Admin\ClientProjectFinanceController::class, 'storeTime'])
        ->name('client-projects.time.store');
    Route::put('client-projects/{client_project}/time/{time_entry}', [\App\Http\Controllers\Admin\ClientProjectFinanceController::class, 'updateTime'])
        ->name('client-projects.time.update');
    Route::delete('client-projects/{client_project}/time/{time_entry}', [\App\Http\Controllers\Admin\ClientProjectFinanceController::class, 'destroyTime'])
        ->name('client-projects.time.destroy');

    Route::post('client-projects/{client_project}/helpers/sync', [\App\Http\Controllers\Admin\ClientProjectHelperController::class, 'syncProjectHelpers'])
        ->name('client-projects.helpers.sync');
    Route::post('client-projects/{client_project}/helper-time', [\App\Http\Controllers\Admin\ClientProjectHelperController::class, 'storeHelperTime'])
        ->name('client-projects.helper-time.store');
    Route::put('client-projects/{client_project}/helper-time/{helper_time_entry}', [\App\Http\Controllers\Admin\ClientProjectHelperController::class, 'updateHelperTime'])
        ->name('client-projects.helper-time.update');
    Route::delete('client-projects/{client_project}/helper-time/{helper_time_entry}', [\App\Http\Controllers\Admin\ClientProjectHelperController::class, 'destroyHelperTime'])
        ->name('client-projects.helper-time.destroy');
    Route::post('client-projects/{client_project}/helper-time/mark-paid', [\App\Http\Controllers\Admin\ClientProjectHelperController::class, 'markHelperTimePaid'])
        ->name('client-projects.helper-time.mark-paid');
    Route::post('client-projects/{client_project}/helper-payments/{helper_payment}/acknowledge', [\App\Http\Controllers\Admin\ClientProjectHelperController::class, 'markHelperPaymentAcknowledged'])
        ->name('client-projects.helper-payments.acknowledge');
    Route::post('client-projects/{client_project}/helper-payments/acknowledge-bulk', [\App\Http\Controllers\Admin\ClientProjectHelperController::class, 'bulkMarkHelperPaymentsAcknowledged'])
        ->name('client-projects.helper-payments.acknowledge-bulk');

    Route::resource('helpers', \App\Http\Controllers\Admin\HelperController::class)->except(['show']);

    Route::resource('vehicles', \App\Http\Controllers\Admin\VehicleController::class)->except(['show']);
    Route::post('vehicles/{vehicle}/activate', [\App\Http\Controllers\Admin\VehicleController::class, 'activate'])
        ->name('vehicles.activate');
    Route::put('vehicles/settings/home-address', [\App\Http\Controllers\Admin\VehicleController::class, 'updateHomeAddress'])
        ->name('vehicles.settings.home-address');
    Route::put('vehicles/settings/gas-price', [\App\Http\Controllers\Admin\VehicleController::class, 'updateGasPrice'])
        ->name('vehicles.settings.gas-price');

    Route::get('client-projects/{client_project}/travel-estimate', [\App\Http\Controllers\Admin\ClientProjectTravelController::class, 'estimate'])
        ->name('client-projects.travel-estimate');

    // Contract (rich-text body, PDF generation, email-to-client).
    Route::get('client-projects/{client_project}/contract/edit', [\App\Http\Controllers\Admin\ClientProjectContractController::class, 'edit'])
        ->name('client-projects.contract.edit');
    Route::post('client-projects/{client_project}/contract/ai-draft', [\App\Http\Controllers\Admin\ClientProjectContractController::class, 'generateAiDraft'])
        ->name('client-projects.contract.ai-draft');
    Route::put('client-projects/{client_project}/contract', [\App\Http\Controllers\Admin\ClientProjectContractController::class, 'update'])
        ->name('client-projects.contract.update');
    Route::get('client-projects/{client_project}/contract/pdf', [\App\Http\Controllers\Admin\ClientProjectContractController::class, 'downloadPdf'])
        ->name('client-projects.contract.pdf');
    Route::post('client-projects/{client_project}/contract/send', [\App\Http\Controllers\Admin\ClientProjectContractController::class, 'send'])
        ->name('client-projects.contract.send');

    // Quotation / Estimate (structured, PDF generation, email-to-client).
    Route::get('client-projects/{client_project}/quotations/{quotation}/edit', [\App\Http\Controllers\Admin\ClientProjectQuotationController::class, 'edit'])
        ->name('client-projects.quotation.edit');
    Route::get('client-projects/{client_project}/quotations/{quotation}/pdf', [\App\Http\Controllers\Admin\ClientProjectQuotationController::class, 'downloadPdf'])
        ->name('client-projects.quotation.pdf');
    Route::post('client-projects/{client_project}/quotations/{quotation}/send', [\App\Http\Controllers\Admin\ClientProjectQuotationController::class, 'send'])
        ->name('client-projects.quotation.send');
    Route::post('client-projects/{client_project}/quotations/{quotation}/sms', [\App\Http\Controllers\Admin\ClientProjectQuotationController::class, 'sendQuotationSms'])
        ->middleware('throttle:12,1')
        ->name('client-projects.quotation.sms');
    Route::post('client-projects/{client_project}/quotations/{quotation}/status', [\App\Http\Controllers\Admin\ClientProjectQuotationController::class, 'updateStatus'])
        ->name('client-projects.quotation.status');
    Route::post('client-projects/{client_project}/quotations/{quotation}/subtotal-override', [\App\Http\Controllers\Admin\ClientProjectQuotationController::class, 'updateSubtotalOverride'])
        ->name('client-projects.quotation.subtotal-override');
    Route::post('client-projects/{client_project}/quotations/{quotation}/pdf-photos', [\App\Http\Controllers\Admin\ClientProjectQuotationController::class, 'updatePdfPhotos'])
        ->name('client-projects.quotation.pdf-photos');
    Route::delete('client-projects/{client_project}/quotations/{quotation}', [\App\Http\Controllers\Admin\ClientProjectQuotationController::class, 'destroy'])
        ->name('client-projects.quotation.destroy');

    // Invoices (structured, PDF generation, email-to-client). Multiple allowed
    // per project — one per installment plus optional manual invoices.
    Route::post('client-projects/{client_project}/invoices', [\App\Http\Controllers\Admin\ClientProjectInvoiceController::class, 'create'])
        ->name('client-projects.invoices.create');
    Route::get('client-projects/{client_project}/invoices/{invoice}/edit', [\App\Http\Controllers\Admin\ClientProjectInvoiceController::class, 'edit'])
        ->name('client-projects.invoices.edit');
    Route::put('client-projects/{client_project}/invoices/{invoice}', [\App\Http\Controllers\Admin\ClientProjectInvoiceController::class, 'update'])
        ->name('client-projects.invoices.update');
    Route::get('client-projects/{client_project}/invoices/{invoice}/pdf', [\App\Http\Controllers\Admin\ClientProjectInvoiceController::class, 'downloadPdf'])
        ->name('client-projects.invoices.pdf');
    Route::get('client-projects/{client_project}/invoices/{invoice}/receipt', [\App\Http\Controllers\Admin\ClientProjectInvoiceController::class, 'downloadReceipt'])
        ->name('client-projects.invoices.receipt');
    Route::post('client-projects/{client_project}/invoices/{invoice}/send', [\App\Http\Controllers\Admin\ClientProjectInvoiceController::class, 'send'])
        ->name('client-projects.invoices.send');
    Route::post('client-projects/{client_project}/invoices/{invoice}/sms', [\App\Http\Controllers\Admin\ClientProjectInvoiceController::class, 'sendInvoiceSms'])
        ->middleware('throttle:12,1')
        ->name('client-projects.invoices.sms');
    Route::post('client-projects/{client_project}/invoices/{invoice}/reminder', [\App\Http\Controllers\Admin\ClientProjectInvoiceController::class, 'sendReminder'])
        ->name('client-projects.invoices.reminder');
    Route::post('client-projects/{client_project}/invoices/{invoice}/reminders/toggle', [\App\Http\Controllers\Admin\ClientProjectInvoiceController::class, 'toggleDailyReminders'])
        ->name('client-projects.invoices.reminders.toggle');
    Route::post('client-projects/{client_project}/invoices/{invoice}/mark-paid', [\App\Http\Controllers\Admin\ClientProjectInvoiceController::class, 'markPaid'])
        ->name('client-projects.invoices.mark-paid');
    Route::delete('client-projects/{client_project}/invoices/{invoice}', [\App\Http\Controllers\Admin\ClientProjectInvoiceController::class, 'destroy'])
        ->name('client-projects.invoices.destroy');

    // Hourly-billing: manual "service confirmation" email to the client once
    // the project dates are locked in.
    Route::post('client-projects/{client_project}/service-confirmation/send', [\App\Http\Controllers\Admin\ClientProjectController::class, 'sendServiceConfirmation'])
        ->name('client-projects.service-confirmation.send');
    // Generic on-demand "your project dates are…" email — works for any
    // billing type and can be re-sent freely (no idempotency flag).
    Route::post('client-projects/{client_project}/schedule-dates/send', [\App\Http\Controllers\Admin\ClientProjectController::class, 'sendScheduleDates'])
        ->name('client-projects.schedule-dates.send');
    Route::post('client-projects/{client_project}/time-tracking-summary/send', [\App\Http\Controllers\Admin\ClientProjectController::class, 'sendTimeTrackingSummary'])
        ->name('client-projects.time-tracking-summary.send');
    Route::post('client-projects/{client_project}/photos', [\App\Http\Controllers\Admin\ClientProjectController::class, 'storePhotos'])
        ->name('client-projects.photos.store');
    Route::delete('client-projects/{client_project}/photos/{photo}', [\App\Http\Controllers\Admin\ClientProjectController::class, 'destroyPhoto'])
        ->name('client-projects.photos.destroy');

    // -----------------------------------------------------------------
    // Standalone Quotation module (separate from per-project quotations).
    //
    // Hub page lists quotation "types" as cards (Fence, Standard, …).
    // Fence runs a structural calculator against a catalogue
    // of editable materials and formula parameters. Fence quotes can be
    // saved standalone or linked into an existing client project by
    // appending a line item to that project's own Quotation.
    // -----------------------------------------------------------------
    Route::get('quotations', [\App\Http\Controllers\Admin\QuotationHubController::class, 'index'])
        ->name('quotations.index');
    Route::post('quotations/work-description/ai', [\App\Http\Controllers\Admin\QuotationHubController::class, 'aiWorkDescription'])
        ->name('quotations.work-description.ai');

    // Fence configuration (materials catalogue + formula parameters).
    Route::get('quotations/fence/config', [\App\Http\Controllers\Admin\FenceConfigController::class, 'index'])
        ->name('quotations.fence.config');
    Route::put('quotations/fence/config/materials', [\App\Http\Controllers\Admin\FenceConfigController::class, 'updateMaterials'])
        ->name('quotations.fence.config.materials.update');
    Route::post('quotations/fence/config/materials/sync/start', [\App\Http\Controllers\Admin\FenceConfigController::class, 'syncMaterialsStart'])
        ->name('quotations.fence.config.materials.sync.start');
    Route::post('quotations/fence/config/materials/sync/step', [\App\Http\Controllers\Admin\FenceConfigController::class, 'syncMaterialsStep'])
        ->name('quotations.fence.config.materials.sync.step');
    Route::put('quotations/fence/config/formula', [\App\Http\Controllers\Admin\FenceConfigController::class, 'updateFormula'])
        ->name('quotations.fence.config.formula.update');

    // Fence quotations CRUD + PDF + project-link.
    Route::get('quotations/fence', [\App\Http\Controllers\Admin\FenceQuotationController::class, 'index'])
        ->name('quotations.fence.index');
    Route::get('quotations/fence/create', [\App\Http\Controllers\Admin\FenceQuotationController::class, 'create'])
        ->name('quotations.fence.create');
    Route::post('quotations/fence', [\App\Http\Controllers\Admin\FenceQuotationController::class, 'store'])
        ->name('quotations.fence.store');
    Route::post('quotations/fence/preview', [\App\Http\Controllers\Admin\FenceQuotationController::class, 'preview'])
        ->name('quotations.fence.preview');
    Route::get('quotations/fence/{fence_quotation}/edit', [\App\Http\Controllers\Admin\FenceQuotationController::class, 'edit'])
        ->name('quotations.fence.edit');
    Route::get('quotations/fence/{fence_quotation}/purchase-list', [\App\Http\Controllers\Admin\FenceQuotationController::class, 'purchaseList'])
        ->name('quotations.fence.purchase-list');
    Route::put('quotations/fence/{fence_quotation}', [\App\Http\Controllers\Admin\FenceQuotationController::class, 'update'])
        ->name('quotations.fence.update');
    Route::post('quotations/fence/{fence_quotation}/link', [\App\Http\Controllers\Admin\FenceQuotationController::class, 'linkToProject'])
        ->name('quotations.fence.link');
    Route::post('quotations/fence/{fence_quotation}/create-project', [\App\Http\Controllers\Admin\FenceQuotationController::class, 'createProjectAndLink'])
        ->name('quotations.fence.create-project');
    Route::delete('quotations/fence/{fence_quotation}', [\App\Http\Controllers\Admin\FenceQuotationController::class, 'destroy'])
        ->name('quotations.fence.destroy');

    // Low deck configuration (materials catalogue + formula parameters).
    Route::get('quotations/low-deck/config', [\App\Http\Controllers\Admin\LowDeckConfigController::class, 'index'])
        ->name('quotations.low-deck.config');
    Route::put('quotations/low-deck/config/materials', [\App\Http\Controllers\Admin\LowDeckConfigController::class, 'updateMaterials'])
        ->name('quotations.low-deck.config.materials.update');
    Route::post('quotations/low-deck/config/materials/sync', [\App\Http\Controllers\Admin\LowDeckConfigController::class, 'syncMaterials'])
        ->name('quotations.low-deck.config.materials.sync');
    Route::put('quotations/low-deck/config/formula', [\App\Http\Controllers\Admin\LowDeckConfigController::class, 'updateFormula'])
        ->name('quotations.low-deck.config.formula.update');
    Route::get('quotations/low-deck/config/work-description', [\App\Http\Controllers\Admin\LowDeckConfigController::class, 'workDescriptionTemplate'])
        ->name('quotations.low-deck.config.work-description');
    Route::put('quotations/low-deck/config/work-description', [\App\Http\Controllers\Admin\LowDeckConfigController::class, 'updateWorkDescriptionTemplate'])
        ->name('quotations.low-deck.config.work-description.update');

    // Low deck quotations CRUD + purchase list + project-link.
    Route::get('quotations/low-deck', [\App\Http\Controllers\Admin\LowDeckQuotationController::class, 'index'])
        ->name('quotations.low-deck.index');
    Route::get('quotations/low-deck/create', [\App\Http\Controllers\Admin\LowDeckQuotationController::class, 'create'])
        ->name('quotations.low-deck.create');
    Route::post('quotations/low-deck', [\App\Http\Controllers\Admin\LowDeckQuotationController::class, 'store'])
        ->name('quotations.low-deck.store');
    Route::post('quotations/low-deck/preview', [\App\Http\Controllers\Admin\LowDeckQuotationController::class, 'preview'])
        ->name('quotations.low-deck.preview');
    Route::get('quotations/low-deck/{low_deck_quotation}/edit', [\App\Http\Controllers\Admin\LowDeckQuotationController::class, 'edit'])
        ->name('quotations.low-deck.edit');
    Route::get('quotations/low-deck/{low_deck_quotation}/purchase-list', [\App\Http\Controllers\Admin\LowDeckQuotationController::class, 'purchaseList'])
        ->name('quotations.low-deck.purchase-list');
    Route::get('quotations/low-deck/{low_deck_quotation}/layout-diagrams', [\App\Http\Controllers\Admin\LowDeckQuotationController::class, 'layoutDiagrams'])
        ->name('quotations.low-deck.layout-diagrams');
    Route::put('quotations/low-deck/{low_deck_quotation}', [\App\Http\Controllers\Admin\LowDeckQuotationController::class, 'update'])
        ->name('quotations.low-deck.update');
    Route::post('quotations/low-deck/{low_deck_quotation}/link', [\App\Http\Controllers\Admin\LowDeckQuotationController::class, 'linkToProject'])
        ->name('quotations.low-deck.link');
    Route::post('quotations/low-deck/{low_deck_quotation}/create-project', [\App\Http\Controllers\Admin\LowDeckQuotationController::class, 'createProjectAndLink'])
        ->name('quotations.low-deck.create-project');
    Route::delete('quotations/low-deck/{low_deck_quotation}', [\App\Http\Controllers\Admin\LowDeckQuotationController::class, 'destroy'])
        ->name('quotations.low-deck.destroy');

    // Standard quotations (materials grid + labour, no PDF in-module).
    Route::get('quotations/standard', [\App\Http\Controllers\Admin\StandardQuotationController::class, 'index'])
        ->name('quotations.standard.index');
    Route::get('quotations/standard/create', [\App\Http\Controllers\Admin\StandardQuotationController::class, 'create'])
        ->name('quotations.standard.create');
    Route::post('quotations/standard', [\App\Http\Controllers\Admin\StandardQuotationController::class, 'store'])
        ->name('quotations.standard.store');
    Route::get('quotations/standard/{standard_quotation}/edit', [\App\Http\Controllers\Admin\StandardQuotationController::class, 'edit'])
        ->name('quotations.standard.edit');
    Route::get('quotations/standard/{standard_quotation}/purchase-list', [\App\Http\Controllers\Admin\StandardQuotationController::class, 'purchaseList'])
        ->name('quotations.standard.purchase-list');
    Route::put('quotations/standard/{standard_quotation}', [\App\Http\Controllers\Admin\StandardQuotationController::class, 'update'])
        ->name('quotations.standard.update');
    Route::post('quotations/standard/{standard_quotation}/link', [\App\Http\Controllers\Admin\StandardQuotationController::class, 'linkToProject'])
        ->name('quotations.standard.link');
    Route::post('quotations/standard/{standard_quotation}/create-project', [\App\Http\Controllers\Admin\StandardQuotationController::class, 'createProjectAndLink'])
        ->name('quotations.standard.create-project');
    Route::delete('quotations/standard/{standard_quotation}', [\App\Http\Controllers\Admin\StandardQuotationController::class, 'destroy'])
        ->name('quotations.standard.destroy');

    // Paving stone quotations (Excel-aligned calculator + editable price DB).
    Route::get('quotations/paving', [PavingQuotationController::class, 'index'])
        ->name('quotations.paving.index');
    Route::get('quotations/paving/create', [PavingQuotationController::class, 'create'])
        ->name('quotations.paving.create');
    Route::get('quotations/paving/price-configuration', [PavingDatabaseController::class, 'edit'])
        ->name('quotations.paving.price-configuration');
    Route::post('quotations/paving', [PavingQuotationController::class, 'store'])
        ->name('quotations.paving.store');
    Route::post('quotations/paving/preview', [PavingQuotationController::class, 'preview'])
        ->name('quotations.paving.preview');
    Route::get('quotations/paving/{paving_quotation}/edit', [PavingQuotationController::class, 'edit'])
        ->name('quotations.paving.edit');
    Route::get('quotations/paving/{paving_quotation}/purchase-list', [PavingQuotationController::class, 'purchaseList'])
        ->name('quotations.paving.purchase-list');
    Route::put('quotations/paving/{paving_quotation}', [PavingQuotationController::class, 'update'])
        ->name('quotations.paving.update');
    Route::post('quotations/paving/{paving_quotation}/link', [PavingQuotationController::class, 'linkToProject'])
        ->name('quotations.paving.link');
    Route::post('quotations/paving/{paving_quotation}/create-project', [PavingQuotationController::class, 'createProjectAndLink'])
        ->name('quotations.paving.create-project');
    Route::delete('quotations/paving/{paving_quotation}', [PavingQuotationController::class, 'destroy'])
        ->name('quotations.paving.destroy');

    Route::post('quotations/paving/database/stones', [PavingDatabaseController::class, 'storeStone'])
        ->name('quotations.paving.database.stones.store');
    Route::put('quotations/paving/database/stones/{paving_stone_type}', [PavingDatabaseController::class, 'updateStone'])
        ->name('quotations.paving.database.stones.update');
    Route::delete('quotations/paving/database/stones/{paving_stone_type}', [PavingDatabaseController::class, 'destroyStone'])
        ->name('quotations.paving.database.stones.destroy');
    Route::post('quotations/paving/database/marbles', [PavingDatabaseController::class, 'storeMarble'])
        ->name('quotations.paving.database.marbles.store');
    Route::put('quotations/paving/database/marbles/{paving_marble_type}', [PavingDatabaseController::class, 'updateMarble'])
        ->name('quotations.paving.database.marbles.update');
    Route::delete('quotations/paving/database/marbles/{paving_marble_type}', [PavingDatabaseController::class, 'destroyMarble'])
        ->name('quotations.paving.database.marbles.destroy');
    Route::put('quotations/paving/database/rates/{paving_material_rate}', [PavingDatabaseController::class, 'updateRate'])
        ->name('quotations.paving.database.rates.update');
    Route::post('quotations/paving/database/tiers/sync', [PavingDatabaseController::class, 'syncTiers'])
        ->name('quotations.paving.database.tiers.sync');

    // Stairs renovation quotations (configurable materials + per-step pricing).
    Route::get('quotations/stairs', [StairsQuotationController::class, 'index'])
        ->name('quotations.stairs.index');
    Route::get('quotations/stairs/create', [StairsQuotationController::class, 'create'])
        ->name('quotations.stairs.create');
    Route::get('quotations/stairs/price-configuration', [StairsDatabaseController::class, 'edit'])
        ->name('quotations.stairs.price-configuration');
    Route::get('quotations/stairs/material-types/{stairs_material_type}/photo', [StairsDatabaseController::class, 'materialPhoto'])
        ->name('quotations.stairs.material-photo');
    Route::get('quotations/stairs/nosing-options/{key}/photo', [StairsDatabaseController::class, 'nosingOptionPhoto'])
        ->where('key', '[a-z0-9_]+')
        ->name('quotations.stairs.nosing-option-photo');
    Route::post('quotations/stairs', [StairsQuotationController::class, 'store'])
        ->name('quotations.stairs.store');
    Route::post('quotations/stairs/preview', [StairsQuotationController::class, 'preview'])
        ->name('quotations.stairs.preview');
    Route::post('quotations/stairs/database', [StairsDatabaseController::class, 'update'])
        ->name('quotations.stairs.database.update');
    Route::get('quotations/stairs/{stairs_quotation}/edit', [StairsQuotationController::class, 'edit'])
        ->name('quotations.stairs.edit');
    Route::put('quotations/stairs/{stairs_quotation}', [StairsQuotationController::class, 'update'])
        ->name('quotations.stairs.update');
    Route::post('quotations/stairs/{stairs_quotation}/link', [StairsQuotationController::class, 'linkToProject'])
        ->name('quotations.stairs.link');
    Route::post('quotations/stairs/{stairs_quotation}/create-project', [StairsQuotationController::class, 'createProjectAndLink'])
        ->name('quotations.stairs.create-project');
    Route::delete('quotations/stairs/{stairs_quotation}', [StairsQuotationController::class, 'destroy'])
        ->name('quotations.stairs.destroy');

    // Painting services quotations (residential interior painting calculator).
    Route::get('quotations/painting', [\App\Http\Controllers\Admin\PaintingQuotationController::class, 'index'])
        ->name('quotations.painting.index');
    Route::get('quotations/painting/create', [\App\Http\Controllers\Admin\PaintingQuotationController::class, 'create'])
        ->name('quotations.painting.create');
    Route::get('quotations/painting/price-configuration', [\App\Http\Controllers\Admin\PaintingDatabaseController::class, 'edit'])
        ->name('quotations.painting.price-configuration');
    Route::post('quotations/painting/database', [\App\Http\Controllers\Admin\PaintingDatabaseController::class, 'update'])
        ->name('quotations.painting.database.update');
    Route::post('quotations/painting', [\App\Http\Controllers\Admin\PaintingQuotationController::class, 'store'])
        ->name('quotations.painting.store');
    Route::post('quotations/painting/preview', [\App\Http\Controllers\Admin\PaintingQuotationController::class, 'preview'])
        ->name('quotations.painting.preview');
    Route::get('quotations/painting/{painting_quotation}/edit', [\App\Http\Controllers\Admin\PaintingQuotationController::class, 'edit'])
        ->name('quotations.painting.edit');
    Route::put('quotations/painting/{painting_quotation}', [\App\Http\Controllers\Admin\PaintingQuotationController::class, 'update'])
        ->name('quotations.painting.update');
    Route::post('quotations/painting/{painting_quotation}/link', [\App\Http\Controllers\Admin\PaintingQuotationController::class, 'linkToProject'])
        ->name('quotations.painting.link');
    Route::post('quotations/painting/{painting_quotation}/create-project', [\App\Http\Controllers\Admin\PaintingQuotationController::class, 'createProjectAndLink'])
        ->name('quotations.painting.create-project');
    Route::delete('quotations/painting/{painting_quotation}', [\App\Http\Controllers\Admin\PaintingQuotationController::class, 'destroy'])
        ->name('quotations.painting.destroy');

    // Flooring quotations (installation calculator + optional client material).
    Route::get('quotations/flooring', [\App\Http\Controllers\Admin\FlooringQuotationController::class, 'index'])
        ->name('quotations.flooring.index');
    Route::get('quotations/flooring/create', [\App\Http\Controllers\Admin\FlooringQuotationController::class, 'create'])
        ->name('quotations.flooring.create');
    Route::get('quotations/flooring/price-configuration', [\App\Http\Controllers\Admin\FlooringDatabaseController::class, 'edit'])
        ->name('quotations.flooring.price-configuration');
    Route::post('quotations/flooring-calculate', [\App\Http\Controllers\Admin\FlooringQuotationController::class, 'preview'])
        ->name('quotations.flooring.calculate');
    Route::post('quotations/flooring/database', [\App\Http\Controllers\Admin\FlooringDatabaseController::class, 'update'])
        ->name('quotations.flooring.database.update');
    Route::post('quotations/flooring', [\App\Http\Controllers\Admin\FlooringQuotationController::class, 'store'])
        ->name('quotations.flooring.store');
    Route::post('quotations/flooring/preview', [\App\Http\Controllers\Admin\FlooringQuotationController::class, 'preview'])
        ->name('quotations.flooring.preview');
    Route::get('quotations/flooring/{flooring_quotation}/edit', [\App\Http\Controllers\Admin\FlooringQuotationController::class, 'edit'])
        ->name('quotations.flooring.edit');
    Route::post('quotations/flooring/{flooring_quotation}/calculate', [\App\Http\Controllers\Admin\FlooringQuotationController::class, 'preview'])
        ->name('quotations.flooring.calculate-existing');
    Route::put('quotations/flooring/{flooring_quotation}', [\App\Http\Controllers\Admin\FlooringQuotationController::class, 'update'])
        ->name('quotations.flooring.update');
    Route::post('quotations/flooring/{flooring_quotation}/link', [\App\Http\Controllers\Admin\FlooringQuotationController::class, 'linkToProject'])
        ->name('quotations.flooring.link');
    Route::post('quotations/flooring/{flooring_quotation}/create-project', [\App\Http\Controllers\Admin\FlooringQuotationController::class, 'createProjectAndLink'])
        ->name('quotations.flooring.create-project');
    Route::delete('quotations/flooring/{flooring_quotation}', [\App\Http\Controllers\Admin\FlooringQuotationController::class, 'destroy'])
        ->name('quotations.flooring.destroy');

    // Tile quotations
    Route::get('quotations/tile', [\App\Http\Controllers\Admin\TileQuotationController::class, 'index'])
        ->name('quotations.tile.index');
    Route::get('quotations/tile/create', [\App\Http\Controllers\Admin\TileQuotationController::class, 'create'])
        ->name('quotations.tile.create');
    Route::get('quotations/tile/price-configuration', [\App\Http\Controllers\Admin\TileDatabaseController::class, 'edit'])
        ->name('quotations.tile.price-configuration');
    Route::put('quotations/tile/price-configuration', [\App\Http\Controllers\Admin\TileDatabaseController::class, 'update'])
        ->name('quotations.tile.price-configuration.update');
    Route::post('quotations/tile', [\App\Http\Controllers\Admin\TileQuotationController::class, 'store'])
        ->name('quotations.tile.store');
    Route::post('quotations/tile/live', [\App\Http\Controllers\Admin\TileQuotationController::class, 'calculateLive'])
        ->name('quotations.tile.live');
    Route::get('quotations/tile/{quotation}/edit', [\App\Http\Controllers\Admin\TileQuotationController::class, 'edit'])
        ->name('quotations.tile.edit');
    Route::put('quotations/tile/{quotation}', [\App\Http\Controllers\Admin\TileQuotationController::class, 'update'])
        ->name('quotations.tile.update');
    Route::post('quotations/tile/{quotation}/link', [\App\Http\Controllers\Admin\TileQuotationController::class, 'linkToProject'])
        ->name('quotations.tile.link');
    Route::delete('quotations/tile/{quotation}', [\App\Http\Controllers\Admin\TileQuotationController::class, 'destroy'])
        ->name('quotations.tile.destroy');
    Route::post('quotations/tile/{quotation}/create-project', [\App\Http\Controllers\Admin\TileQuotationController::class, 'createProjectAndLink'])
        ->name('quotations.tile.create-project');

    // Basement renovation quotations (multi-section wizard).
    Route::get('quotations/basement', [\App\Http\Controllers\Admin\BasementQuotationController::class, 'index'])
        ->name('quotations.basement.index');
    Route::get('quotations/basement/create', [\App\Http\Controllers\Admin\BasementQuotationController::class, 'create'])
        ->name('quotations.basement.create');
    Route::get('quotations/basement/price-configuration', [\App\Http\Controllers\Admin\BasementDatabaseController::class, 'edit'])
        ->name('quotations.basement.price-configuration');
    Route::post('quotations/basement-calculate', [\App\Http\Controllers\Admin\BasementQuotationController::class, 'preview'])
        ->name('quotations.basement.calculate');
    Route::post('quotations/basement/database', [\App\Http\Controllers\Admin\BasementDatabaseController::class, 'update'])
        ->name('quotations.basement.database.update');
    Route::post('quotations/basement', [\App\Http\Controllers\Admin\BasementQuotationController::class, 'store'])
        ->name('quotations.basement.store');
    Route::post('quotations/basement/preview', [\App\Http\Controllers\Admin\BasementQuotationController::class, 'preview'])
        ->name('quotations.basement.preview');
    Route::get('quotations/basement/{basement_quotation}/edit', [\App\Http\Controllers\Admin\BasementQuotationController::class, 'edit'])
        ->name('quotations.basement.edit');
    Route::post('quotations/basement/{basement_quotation}/calculate', [\App\Http\Controllers\Admin\BasementQuotationController::class, 'preview'])
        ->name('quotations.basement.calculate-existing');
    Route::put('quotations/basement/{basement_quotation}', [\App\Http\Controllers\Admin\BasementQuotationController::class, 'update'])
        ->name('quotations.basement.update');
    Route::post('quotations/basement/{basement_quotation}/link', [\App\Http\Controllers\Admin\BasementQuotationController::class, 'linkToProject'])
        ->name('quotations.basement.link');
    Route::post('quotations/basement/{basement_quotation}/create-project', [\App\Http\Controllers\Admin\BasementQuotationController::class, 'createProjectAndLink'])
        ->name('quotations.basement.create-project');
    Route::delete('quotations/basement/{basement_quotation}', [\App\Http\Controllers\Admin\BasementQuotationController::class, 'destroy'])
        ->name('quotations.basement.destroy');

    Route::resource('client-projects', \App\Http\Controllers\Admin\ClientProjectController::class)
        ->parameters(['client-projects' => 'client_project']);

    // Email / SMS audit log — read-only history of every message sent
    // to clients (and admin-bound notifications).
    Route::get('communications', [\App\Http\Controllers\Admin\CommunicationLogController::class, 'index'])
        ->name('communications.index');

    // Utilities — collection of standalone calculation tools.
    Route::get('ai-instructions', [\App\Http\Controllers\Admin\AiInstructionController::class, 'index'])
        ->name('ai-instructions.index');
    Route::get('ai-instructions/{ai_instruction}/edit', [\App\Http\Controllers\Admin\AiInstructionController::class, 'edit'])
        ->name('ai-instructions.edit');
    Route::put('ai-instructions/{ai_instruction}', [\App\Http\Controllers\Admin\AiInstructionController::class, 'update'])
        ->name('ai-instructions.update');

    Route::resource('supplier-sites', \App\Http\Controllers\Admin\SupplierSiteController::class)
        ->except(['show'])
        ->names('supplier-sites');

    Route::get('image-creation', [\App\Http\Controllers\Admin\ImageCreationController::class, 'index'])
        ->name('image-creation.index');
    Route::get('image-creation/create', [\App\Http\Controllers\Admin\ImageCreationController::class, 'create'])
        ->name('image-creation.create');
    Route::get('image-creation/nano-banana-2', [\App\Http\Controllers\Admin\ImageCreationController::class, 'nanoBanana2'])
        ->name('image-creation.nano-banana-2');
    Route::get('image-creation/modify', [\App\Http\Controllers\Admin\ImageCreationController::class, 'modify'])
        ->name('image-creation.modify');
    Route::post('image-creation/generate', [\App\Http\Controllers\Admin\ImageCreationController::class, 'generate'])
        ->name('image-creation.generate');
    Route::post('image-creation/modify', [\App\Http\Controllers\Admin\ImageCreationController::class, 'modifyImage'])
        ->name('image-creation.modify.store');
    Route::post('image-creation/improve-prompt', [\App\Http\Controllers\Admin\ImageCreationController::class, 'improvePrompt'])
        ->name('image-creation.improve-prompt');
    Route::get('image-creation/{ai_generated_image}/download/{format}', [\App\Http\Controllers\Admin\ImageCreationController::class, 'download'])
        ->where('format', 'original|webp')
        ->name('image-creation.download');
    Route::delete('image-creation/{ai_generated_image}', [\App\Http\Controllers\Admin\ImageCreationController::class, 'destroy'])
        ->name('image-creation.destroy');

    Route::get('utilities', [\App\Http\Controllers\Admin\UtilitiesController::class, 'index'])
        ->name('utilities.index');
    Route::get('utilities/pillar-spindle', [\App\Http\Controllers\Admin\UtilitiesController::class, 'pillarSpindle'])
        ->name('utilities.pillar-spindle');
    Route::post('utilities/pillar-spindle/pdf', [\App\Http\Controllers\Admin\UtilitiesController::class, 'pillarSpindlePdf'])
        ->name('utilities.pillar-spindle.pdf');
    Route::get('utilities/lumber-board', [\App\Http\Controllers\Admin\UtilitiesController::class, 'lumberBoard'])
        ->name('utilities.lumber-board');
    Route::post('utilities/lumber-board/pdf', [\App\Http\Controllers\Admin\UtilitiesController::class, 'lumberBoardPdf'])
        ->name('utilities.lumber-board.pdf');
    Route::post('utilities/calculations', [\App\Http\Controllers\Admin\UtilitiesController::class, 'storeCalculation'])
        ->name('utilities.calculations.store');
    Route::put('utilities/calculations/{utility_calculation}', [\App\Http\Controllers\Admin\UtilitiesController::class, 'updateCalculation'])
        ->name('utilities.calculations.update');
    Route::delete('utilities/calculations/{utility_calculation}', [\App\Http\Controllers\Admin\UtilitiesController::class, 'destroyCalculation'])
        ->name('utilities.calculations.destroy');

    Route::get('utilities/measurement-conversion', [\App\Http\Controllers\Admin\MeasurementConversionController::class, 'index'])
        ->name('utilities.measurement-conversion');
    Route::post('utilities/measurement-conversion/preview', [\App\Http\Controllers\Admin\MeasurementConversionController::class, 'preview'])
        ->name('utilities.measurement-conversion.preview');
    Route::post('utilities/measurement-conversion/projects', [\App\Http\Controllers\Admin\MeasurementConversionController::class, 'storeProject'])
        ->name('utilities.measurement-conversion.projects.store');
    Route::put('utilities/measurement-conversion/projects/{measurement_project}', [\App\Http\Controllers\Admin\MeasurementConversionController::class, 'updateProject'])
        ->name('utilities.measurement-conversion.projects.update');
    Route::delete('utilities/measurement-conversion/projects/{measurement_project}', [\App\Http\Controllers\Admin\MeasurementConversionController::class, 'destroyProject'])
        ->name('utilities.measurement-conversion.projects.destroy');
    Route::post('utilities/measurement-conversion/projects/{measurement_project}/rows', [\App\Http\Controllers\Admin\MeasurementConversionController::class, 'storeRow'])
        ->name('utilities.measurement-conversion.rows.store');
    Route::delete('utilities/measurement-conversion/rows/{measurement_row}', [\App\Http\Controllers\Admin\MeasurementConversionController::class, 'destroyRow'])
        ->name('utilities.measurement-conversion.rows.destroy');

    Route::get('utilities/room-area', [\App\Http\Controllers\Admin\RoomAreaController::class, 'index'])
        ->name('utilities.room-area');
    Route::post('utilities/room-area/validate', [\App\Http\Controllers\Admin\RoomAreaController::class, 'validateRoom'])
        ->name('utilities.room-area.validate');
    Route::post('utilities/room-area/projects', [\App\Http\Controllers\Admin\RoomAreaController::class, 'storeProject'])
        ->name('utilities.room-area.projects.store');
    Route::put('utilities/room-area/projects/{room_area_project}', [\App\Http\Controllers\Admin\RoomAreaController::class, 'updateProject'])
        ->name('utilities.room-area.projects.update');
    Route::delete('utilities/room-area/projects/{room_area_project}', [\App\Http\Controllers\Admin\RoomAreaController::class, 'destroyProject'])
        ->name('utilities.room-area.projects.destroy');
    Route::post('utilities/room-area/projects/{room_area_project}/rooms', [\App\Http\Controllers\Admin\RoomAreaController::class, 'storeRoom'])
        ->name('utilities.room-area.rooms.store');
    Route::put('utilities/room-area/rooms/{room_area_room}', [\App\Http\Controllers\Admin\RoomAreaController::class, 'updateRoom'])
        ->name('utilities.room-area.rooms.update');
    Route::delete('utilities/room-area/rooms/{room_area_room}', [\App\Http\Controllers\Admin\RoomAreaController::class, 'destroyRoom'])
        ->name('utilities.room-area.rooms.destroy');
    Route::post('utilities/room-area/rooms/{room_area_room}/background', [\App\Http\Controllers\Admin\RoomAreaController::class, 'uploadBackground'])
        ->name('utilities.room-area.rooms.background.upload');
    Route::get('utilities/room-area/rooms/{room_area_room}/background', [\App\Http\Controllers\Admin\RoomAreaController::class, 'background'])
        ->name('utilities.room-area.rooms.background');
    Route::delete('utilities/room-area/rooms/{room_area_room}/background', [\App\Http\Controllers\Admin\RoomAreaController::class, 'destroyBackground'])
        ->name('utilities.room-area.rooms.background.destroy');
});
