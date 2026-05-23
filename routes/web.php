<?php

use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\ClientProjectInvoiceController;
use App\Http\Controllers\Admin\ClientProjectQuotationController;
use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\GooglePhotosController;
use App\Http\Controllers\Admin\PavingDatabaseController;
use App\Http\Controllers\Admin\PavingQuotationController;
use App\Http\Controllers\Admin\StairsDatabaseController;
use App\Http\Controllers\Admin\StairsQuotationController;
use App\Http\Controllers\Admin\ProjectController;
use App\Http\Controllers\ContactFormController;
use App\Models\Project;
use Illuminate\Support\Facades\Route;

Route::post('/contact-email', [ContactFormController::class, 'submit'])
    ->middleware('throttle:8,1')
    ->name('contact.submit');

Route::view('/warranty', 'site.warranty')->name('warranty');
Route::view('/privacy-policy', 'site.privacy-policy')->name('privacy-policy');
Route::view('/terms-and-conditions', 'site.terms-and-conditions')->name('terms-and-conditions');

Route::permanentRedirect('/services/custom-kitchen-remodel', '/');

Route::get('/sitemap.xml', function () {
    return response()
        ->view('site.sitemap-xml', [], 200)
        ->header('Content-Type', 'application/xml; charset=UTF-8');
})->name('sitemap');

Route::get('/robots.txt', function () {
    return response(\App\Support\RobotsTxtGenerator::generate(), 200)
        ->header('Content-Type', 'text/plain; charset=UTF-8');
})->name('robots');

/** Signed link (no login) so clients can open invoice PDFs from SMS — see ClientProjectInvoiceController::sendInvoiceSms. */
Route::get('/client/invoice/{client_project}/{invoice}', [ClientProjectInvoiceController::class, 'guestDownloadInvoicePdf'])
    ->middleware('signed')
    ->name('public.client-project.invoice-pdf');
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

    return view('site.home', compact('projects', 'projectFilterCategories'));
});

Route::middleware('guest')->group(function () {
    Route::get('/admin/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/admin/login', [LoginController::class, 'login']);
});

Route::middleware('auth')->get('/dashboard', [DashboardController::class, 'index'])
    ->name('admin.dashboard');

Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
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

    // Portfolio (a.k.a. "Latest Projects Photos") — manages Project/ProjectPhoto used on the public home page.
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

    // Client Projects management (quote → completion lifecycle).
    Route::get('client-projects/calendar', [\App\Http\Controllers\Admin\ClientProjectController::class, 'calendar'])
        ->name('client-projects.calendar');
    Route::post('calendar/personal-events', [\App\Http\Controllers\Admin\AdminCalendarEventController::class, 'store'])
        ->name('calendar.personal-events.store');
    Route::put('calendar/personal-events/{admin_calendar_event}', [\App\Http\Controllers\Admin\AdminCalendarEventController::class, 'update'])
        ->name('calendar.personal-events.update');
    Route::delete('calendar/personal-events/{admin_calendar_event}', [\App\Http\Controllers\Admin\AdminCalendarEventController::class, 'destroy'])
        ->name('calendar.personal-events.destroy');
    Route::post('client-projects/{client_project}/status', [\App\Http\Controllers\Admin\ClientProjectStatusController::class, 'update'])
        ->name('client-projects.status.update');
    Route::post('client-projects/{client_project}/installments', [\App\Http\Controllers\Admin\ClientProjectFinanceController::class, 'saveInstallments'])
        ->name('client-projects.installments.save');
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

    Route::get('client-projects/{client_project}/travel-estimate', [\App\Http\Controllers\Admin\ClientProjectTravelController::class, 'estimate'])
        ->name('client-projects.travel-estimate');

    // Contract (rich-text body, PDF generation, email-to-client).
    Route::get('client-projects/{client_project}/contract/edit', [\App\Http\Controllers\Admin\ClientProjectContractController::class, 'edit'])
        ->name('client-projects.contract.edit');
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

    // Fence configuration (materials catalogue + formula parameters).
    Route::get('quotations/fence/config', [\App\Http\Controllers\Admin\FenceConfigController::class, 'index'])
        ->name('quotations.fence.config');
    Route::put('quotations/fence/config/materials', [\App\Http\Controllers\Admin\FenceConfigController::class, 'updateMaterials'])
        ->name('quotations.fence.config.materials.update');
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

    // Standard quotations (materials grid + labour, no PDF in-module).
    Route::get('quotations/standard', [\App\Http\Controllers\Admin\StandardQuotationController::class, 'index'])
        ->name('quotations.standard.index');
    Route::get('quotations/standard/create', [\App\Http\Controllers\Admin\StandardQuotationController::class, 'create'])
        ->name('quotations.standard.create');
    Route::post('quotations/standard', [\App\Http\Controllers\Admin\StandardQuotationController::class, 'store'])
        ->name('quotations.standard.store');
    Route::get('quotations/standard/{standard_quotation}/edit', [\App\Http\Controllers\Admin\StandardQuotationController::class, 'edit'])
        ->name('quotations.standard.edit');
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
    Route::post('quotations/paving', [PavingQuotationController::class, 'store'])
        ->name('quotations.paving.store');
    Route::post('quotations/paving/preview', [PavingQuotationController::class, 'preview'])
        ->name('quotations.paving.preview');
    Route::get('quotations/paving/{paving_quotation}/edit', [PavingQuotationController::class, 'edit'])
        ->name('quotations.paving.edit');
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

    Route::resource('client-projects', \App\Http\Controllers\Admin\ClientProjectController::class)
        ->parameters(['client-projects' => 'client_project']);

    // Email / SMS audit log — read-only history of every message sent
    // to clients (and admin-bound notifications).
    Route::get('communications', [\App\Http\Controllers\Admin\CommunicationLogController::class, 'index'])
        ->name('communications.index');
});
