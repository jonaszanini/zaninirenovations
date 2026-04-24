<?php

use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\GooglePhotosController;
use App\Http\Controllers\Admin\ProjectController;
use App\Http\Controllers\ContactFormController;
use App\Models\Project;
use Illuminate\Support\Facades\Route;

Route::post('/contact-email', [ContactFormController::class, 'submit'])
    ->middleware('throttle:8,1')
    ->name('contact.submit');

Route::view('/warranty', 'site.warranty')->name('warranty');

Route::view('/services/custom-kitchen-remodel', 'site.service-kitchen')->name('service.kitchen');

Route::get('/sitemap.xml', function () {
    return response()
        ->view('site.sitemap-xml', [], 200)
        ->header('Content-Type', 'application/xml; charset=UTF-8');
})->name('sitemap');

Route::get('/robots.txt', function () {
    $base = rtrim((string) config('app.url'), '/');
    $lines = [
        'User-agent: *',
        'Disallow: /admin',
        'Disallow: /admin/',
        'Allow: /',
        '',
        'Sitemap: '.$base.'/sitemap.xml',
        '',
    ];

    return response(implode("\n", $lines), 200)
        ->header('Content-Type', 'text/plain; charset=UTF-8');
})->name('robots');

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

Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/', function () {
        return redirect()->route('admin.client-projects.index');
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

    // Client Projects management (quote → completion lifecycle).
    Route::get('client-projects/calendar', [\App\Http\Controllers\Admin\ClientProjectController::class, 'calendar'])
        ->name('client-projects.calendar');
    Route::post('client-projects/{client_project}/status', [\App\Http\Controllers\Admin\ClientProjectStatusController::class, 'update'])
        ->name('client-projects.status.update');
    Route::post('client-projects/{client_project}/installments', [\App\Http\Controllers\Admin\ClientProjectFinanceController::class, 'saveInstallments'])
        ->name('client-projects.installments.save');
    Route::post('client-projects/{client_project}/installments/{installment}/paid', [\App\Http\Controllers\Admin\ClientProjectFinanceController::class, 'togglePaid'])
        ->name('client-projects.installments.toggle-paid');
    Route::post('client-projects/{client_project}/expenses', [\App\Http\Controllers\Admin\ClientProjectFinanceController::class, 'storeExpense'])
        ->name('client-projects.expenses.store');
    Route::delete('client-projects/{client_project}/expenses/{expense}', [\App\Http\Controllers\Admin\ClientProjectFinanceController::class, 'destroyExpense'])
        ->name('client-projects.expenses.destroy');
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
    Route::get('client-projects/{client_project}/quotation/edit', [\App\Http\Controllers\Admin\ClientProjectQuotationController::class, 'edit'])
        ->name('client-projects.quotation.edit');
    Route::put('client-projects/{client_project}/quotation', [\App\Http\Controllers\Admin\ClientProjectQuotationController::class, 'update'])
        ->name('client-projects.quotation.update');
    Route::get('client-projects/{client_project}/quotation/pdf', [\App\Http\Controllers\Admin\ClientProjectQuotationController::class, 'downloadPdf'])
        ->name('client-projects.quotation.pdf');
    Route::post('client-projects/{client_project}/quotation/send', [\App\Http\Controllers\Admin\ClientProjectQuotationController::class, 'send'])
        ->name('client-projects.quotation.send');

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
    Route::post('client-projects/{client_project}/time-tracking-summary/send', [\App\Http\Controllers\Admin\ClientProjectController::class, 'sendTimeTrackingSummary'])
        ->name('client-projects.time-tracking-summary.send');

    // -----------------------------------------------------------------
    // Standalone Quotation module (separate from per-project quotations).
    //
    // Hub page lists quotation "types" as cards; today the only one wired
    // up is Fence, which runs a structural calculator against a catalogue
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

    Route::resource('client-projects', \App\Http\Controllers\Admin\ClientProjectController::class)
        ->parameters(['client-projects' => 'client_project']);
});
