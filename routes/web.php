<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/


// Route::get('/', function () {
//     return view('welcome');
// });
Route::get('/',[App\Http\Controllers\Auth\LoginController::class,'showLoginForm']);

Auth::routes();

// Route /home dihapus — semua user diarahkan ke /admin/home setelah login

/*------------------------------------------
--------------------------------------------
All Normal Users Routes List
--------------------------------------------
--------------------------------------------*/
// Route::middleware(['auth', "prefix" => "user/", 'user-access:user'])->group(function () {
//     Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
// });

/*------------------------------------------
--------------------------------------------
All Super Admin Routes List
--------------------------------------------
--------------------------------------------*/
Route::middleware(['auth', 'user-access:super-admin'])->prefix('super-admin/')->group(function () {
    Route::get('home', [App\Http\Controllers\AdminController::class, 'superAdminHome'])->name('super.admin.home');
    //User Route List
    Route::get('user', [App\Http\Controllers\AdminController::class, 'user'])->name('super.admin.user');
    Route::post('store-user', [App\Http\Controllers\AdminController::class, 'storeUser'])->name('super.admin.storeUser');
    Route::get('edit-user/{id}', [App\Http\Controllers\AdminController::class, 'editUser'])->name('super.admin.editUser');
    Route::put('update-user/{id}', [App\Http\Controllers\AdminController::class, 'updateUser'])->name('super.admin.updateUser');
    Route::delete('delete-user/{id}', [App\Http\Controllers\AdminController::class, 'destroyUser'])->name('super.admin.destroyUser');

});

/*------------------------------------------
--------------------------------------------
All Admin Routes List
--------------------------------------------
--------------------------------------------*/

Route::middleware(['auth', 'is_admin'])->prefix('admin/')->group(function () {
    Route::get('home', [App\Http\Controllers\AdminController::class, 'adminHome'])->name('admin.home');
    Route::post('beranda/quicklinks', [App\Http\Controllers\AdminController::class, 'updateQuicklinks'])->name('admin.beranda.quicklinks');
    Route::get('analytics', [App\Http\Controllers\AdminController::class, 'analyticsDashboard'])->name('admin.analytics');
    Route::get('analytics/filter-imunisasi', [App\Http\Controllers\AdminController::class, 'analyticsFilterImunisasi'])->name('admin.analytics.filterImunisasi');
    Route::get('map', [App\Http\Controllers\AdminController::class, 'mapDashboard'])->name('admin.map');
    Route::get('api/map-data', [App\Http\Controllers\AdminController::class, 'getMapData'])->name('admin.mapData');
    Route::get('early-warning', [App\Http\Controllers\AdminController::class, 'earlyWarningSystem'])->name('admin.earlyWarning');
    Route::get('early-warning/export-vaccine-needs', [App\Http\Controllers\AdminController::class, 'exportVaccineNeeds'])->name('admin.exportVaccineNeeds');
    //Anak Route List
    Route::get('data-dasar-anak', [App\Http\Controllers\AdminController::class, 'anak'])->name('admin.anak');
    Route::get('get-data-dasar-anak', [App\Http\Controllers\AdminController::class, 'getAnak'])->name('admin.getAnak');
    Route::get('create-data-dasar-anak', [App\Http\Controllers\AdminController::class, 'createAnak'])->name('admin.createAnak');
    Route::get('get-kel-dasar-anak/{id}', [App\Http\Controllers\AdminController::class, 'getKelAnak'])->name('admin.getKelAnak');
    Route::get('get-puskesmas-dasar-anak/{id}', [App\Http\Controllers\AdminController::class, 'getPuskesmasAnak'])->name('admin.getPuskesmasAnak');
    Route::get('get-posyandu-dasar-anak/{id}', [App\Http\Controllers\AdminController::class, 'getPosyanduAnak'])->name('admin.getPosyanduAnak');
    Route::get('get-rt-dasar-anak/{id}', [App\Http\Controllers\AdminController::class, 'getRtAnak'])->name('admin.getRtAnak');
    Route::get('get-rt-by-kel-anak/{id}', [App\Http\Controllers\AdminController::class, 'getRtByKelAnak'])->name('admin.getRtByKelAnak');
    Route::get('get-posyandu-by-kel-anak/{id}', [App\Http\Controllers\AdminController::class, 'getPosyanduByKelAnak'])->name('admin.getPosyanduByKelAnak');
    Route::post('store-data-dasar-anak', [App\Http\Controllers\AdminController::class, 'storeAnak'])->name('admin.storeAnak');
    Route::post('import-kohort', [App\Http\Controllers\AdminController::class, 'importKohort'])->name('admin.importKohort');
    Route::get('import-kohort-status', [App\Http\Controllers\AdminController::class, 'importKohortStatus'])->name('admin.importKohortStatus');

    // Import CSV terstandarisasi (Anak / Pengukuran Berkala / Imunisasi)
    Route::prefix('import-csv')->group(function () {
        Route::get('/',           [App\Http\Controllers\ImportCsvController::class, 'index'])          ->name('admin.importCsv.index');
        Route::post('anak',       [App\Http\Controllers\ImportCsvController::class, 'uploadAnak'])       ->name('admin.importCsv.anak');
        Route::post('capil',      [App\Http\Controllers\ImportCsvController::class, 'uploadCapil'])      ->name('admin.importCsv.capil');
        Route::post('pengukuran', [App\Http\Controllers\ImportCsvController::class, 'uploadPengukuran']) ->name('admin.importCsv.pengukuran');
        Route::post('imunisasi',  [App\Http\Controllers\ImportCsvController::class, 'uploadImunisasi'])  ->name('admin.importCsv.imunisasi');
        Route::post('hasil-lab',  [App\Http\Controllers\ImportCsvController::class, 'uploadHasilLab'])  ->name('admin.importCsv.hasilLab');
        Route::post('ukur',       [App\Http\Controllers\ImportCsvController::class, 'uploadUkur'])        ->name('admin.importCsv.ukur');
        Route::post('reimport/{log}', [App\Http\Controllers\ImportCsvController::class, 'reimport'])     ->name('admin.importCsv.reimport');
        Route::get('status',      [App\Http\Controllers\ImportCsvController::class, 'importStatus'])     ->name('admin.importCsv.status');
        Route::delete('log/{log}',[App\Http\Controllers\ImportCsvController::class, 'destroyLog'])       ->name('admin.importCsv.destroyLog');
        Route::get('template/{type}', [App\Http\Controllers\ImportCsvController::class, 'downloadTemplate'])->name('admin.importCsv.template');
    });

    // Dashboard Gizi & Operasi Timbang
    Route::prefix('timbang-dashboard')->group(function () {
        Route::get('/',            [App\Http\Controllers\TimbangDashboardController::class, 'index'])    ->name('admin.timbang.dashboard');
        Route::get('api/ringkasan',[App\Http\Controllers\TimbangDashboardController::class, 'ringkasan'])->name('admin.timbang.ringkasan');
        Route::get('api/gizi',     [App\Http\Controllers\TimbangDashboardController::class, 'gizi'])     ->name('admin.timbang.gizi');
        Route::get('api/tren',     [App\Http\Controllers\TimbangDashboardController::class, 'tren'])     ->name('admin.timbang.tren');
        Route::get('api/coverage', [App\Http\Controllers\TimbangDashboardController::class, 'coverage']) ->name('admin.timbang.coverage');
        Route::get('api/program',  [App\Http\Controllers\TimbangDashboardController::class, 'program'])  ->name('admin.timbang.program');
        Route::get('api/daftar',   [App\Http\Controllers\TimbangDashboardController::class, 'daftar'])   ->name('admin.timbang.daftar');
        Route::get('api/daftar/export', [App\Http\Controllers\TimbangDashboardController::class, 'daftarExport'])->name('admin.timbang.daftar.export');
    });

    Route::get('edit-data-dasar-anak/{id}', [App\Http\Controllers\AdminController::class, 'editAnak'])->name('admin.editAnak');
    Route::get('show-data-dasar-anak/{id}', [App\Http\Controllers\AdminController::class, 'showAnak'])->name('admin.showAnak');
    Route::get('chart-data-dasar-anak/{id}', [App\Http\Controllers\AdminController::class, 'chartAnak'])->name('admin.chartAnak');
    Route::get('get-chart-data-dasar-anak/{id}', [App\Http\Controllers\AdminController::class, 'getChartAnak'])->name('admin.getChartAnak');
    Route::put('update-data-dasar-anak/{id}', [App\Http\Controllers\AdminController::class, 'updateAnak'])->name('admin.updateAnak');
    Route::delete('destroy-data-dasar-anak/{id}', [App\Http\Controllers\AdminController::class, 'destroyAnak'])->name('admin.destroyAnak');
    //Data Anak Route List
    Route::get('data-anak/{id}', [App\Http\Controllers\AdminController::class, 'dataAnak'])->name('admin.dataAnak');
    Route::post('store-data-anak', [App\Http\Controllers\AdminController::class, 'storeDataAnak'])->name('admin.storeDataAnak');
    Route::put('update-data-anak/{id}', [App\Http\Controllers\AdminController::class, 'updateDataAnak'])->name('admin.updateDataAnak');
    //Data Imunisasi Lengkap
    Route::get('imunisasi-lengkap/{id}', [App\Http\Controllers\AdminController::class, 'imunisasiLengkap'])->name('admin.imunisasiLengkap');
    Route::get('jadwal-imunisasi/{id}', [App\Http\Controllers\AdminController::class, 'jadwalImunisasi'])->name('admin.jadwalImunisasi');
    Route::post('store-imunisasi', [App\Http\Controllers\AdminController::class, 'storeImunisasiDetail'])->name('admin.storeImunisasiDetail');
    Route::put('update-imunisasi-detail/{id}', [App\Http\Controllers\AdminController::class, 'updateImunisasiDetail'])->name('admin.updateImunisasiDetail');
    Route::delete('delete-imunisasi/{id}', [App\Http\Controllers\AdminController::class, 'deleteImunisasiDetail'])->name('admin.deleteImunisasiDetail');
    Route::get('imunisasi-dashboard', [App\Http\Controllers\AdminController::class, 'imunisasiDashboard'])->name('admin.imunisasiDashboard');
    //Data Export Anak
    Route::get('export', [App\Http\Controllers\AdminController::class, 'exportView'])->name('admin.exportView');
    Route::post('formViewExport', [App\Http\Controllers\AdminController::class, 'formViewExport'])->name('admin.formViewExport');
    Route::get('formViewExportExcel', [App\Http\Controllers\AdminController::class, 'formViewExportExcel'])->name('admin.formViewExportExcel');
    Route::get('exportAllExcel', [App\Http\Controllers\AdminController::class, 'exportAllExcel'])->name('admin.exportAllExcel');
    Route::post('exportFormExcel', [App\Http\Controllers\AdminController::class, 'exportExcel'])->name('admin.exportFormExcel');
    //Ibu Route List
    Route::get('data-ibu', [App\Http\Controllers\AdminController::class, 'ibu'])->name('admin.ibu');
    //Ibu Hamil Route List
    Route::get('data-ibu-hamil', [App\Http\Controllers\AdminController::class, 'ibuHamil'])->name('admin.ibuHamil');

    /*------------------------------------------
    Master Data Routes (superadmin only, enforced in controller)
    --------------------------------------------*/
    Route::prefix('master-data/vaksin')->group(function () {
        Route::get('/', [App\Http\Controllers\MasterDataVaksinController::class, 'index'])
             ->name('admin.masterdata.vaksin.index');
        Route::get('get-data', [App\Http\Controllers\MasterDataVaksinController::class, 'getData'])
             ->name('admin.masterdata.vaksin.getData');
        Route::post('store', [App\Http\Controllers\MasterDataVaksinController::class, 'store'])
             ->name('admin.masterdata.vaksin.store');
        Route::put('update/{id}', [App\Http\Controllers\MasterDataVaksinController::class, 'update'])
             ->name('admin.masterdata.vaksin.update');
        Route::patch('toggle-status/{id}', [App\Http\Controllers\MasterDataVaksinController::class, 'toggleStatus'])
             ->name('admin.masterdata.vaksin.toggleStatus');
        Route::delete('destroy/{id}', [App\Http\Controllers\MasterDataVaksinController::class, 'destroy'])
             ->name('admin.masterdata.vaksin.destroy');
        Route::patch('restore/{id}', [App\Http\Controllers\MasterDataVaksinController::class, 'restore'])
             ->name('admin.masterdata.vaksin.restore');
    });

    Route::prefix('master-data/penyakit')->group(function () {
        Route::get('/', [App\Http\Controllers\MasterDataPenyakitController::class, 'index'])
             ->name('admin.masterdata.penyakit.index');
        Route::get('get-data', [App\Http\Controllers\MasterDataPenyakitController::class, 'getData'])
             ->name('admin.masterdata.penyakit.getData');
        Route::post('store', [App\Http\Controllers\MasterDataPenyakitController::class, 'store'])
             ->name('admin.masterdata.penyakit.store');
        Route::put('update/{id}', [App\Http\Controllers\MasterDataPenyakitController::class, 'update'])
             ->name('admin.masterdata.penyakit.update');
        Route::patch('toggle-status/{id}', [App\Http\Controllers\MasterDataPenyakitController::class, 'toggleStatus'])
             ->name('admin.masterdata.penyakit.toggleStatus');
        Route::delete('destroy/{id}', [App\Http\Controllers\MasterDataPenyakitController::class, 'destroy'])
             ->name('admin.masterdata.penyakit.destroy');
        Route::patch('restore/{id}', [App\Http\Controllers\MasterDataPenyakitController::class, 'restore'])
             ->name('admin.masterdata.penyakit.restore');
    });

    Route::prefix('master-data/penduduk')->group(function () {
        Route::get('/', [App\Http\Controllers\MasterDataPendudukController::class, 'index'])
             ->name('admin.masterdata.penduduk.index');
        Route::get('get-data', [App\Http\Controllers\MasterDataPendudukController::class, 'getData'])
             ->name('admin.masterdata.penduduk.getData');
        Route::post('store', [App\Http\Controllers\MasterDataPendudukController::class, 'store'])
             ->name('admin.masterdata.penduduk.store');
        Route::put('update/{id}', [App\Http\Controllers\MasterDataPendudukController::class, 'update'])
             ->name('admin.masterdata.penduduk.update');
        Route::delete('destroy/{id}', [App\Http\Controllers\MasterDataPendudukController::class, 'destroy'])
             ->name('admin.masterdata.penduduk.destroy');
    });

    /*------------------------------------------
    Export Data Routes
    --------------------------------------------*/
    Route::prefix('export-imunisasi')->group(function () {
        Route::get('/', [App\Http\Controllers\ExportImunisasiController::class, 'index'])
             ->name('admin.export.imunisasi.index');
        Route::get('get-data', [App\Http\Controllers\ExportImunisasiController::class, 'getData'])
             ->name('admin.export.imunisasi.getData');
        Route::get('download', [App\Http\Controllers\ExportImunisasiController::class, 'download'])
             ->name('admin.export.imunisasi.download');
        Route::get('download-agregat', [App\Http\Controllers\ExportImunisasiController::class, 'downloadAgregat'])
             ->name('admin.export.imunisasi.downloadAgregat');
    });

    /*------------------------------------------
    Epidemiology Surveillance Routes
    --------------------------------------------*/
    Route::prefix('epidemiologi/')->group(function () {
        // Dashboard & Analytics
        Route::get('dashboard', [App\Http\Controllers\EpidemiologiController::class, 'dashboard'])
             ->name('admin.epidemiologi.dashboard');
        Route::get('map', [App\Http\Controllers\EpidemiologiController::class, 'mapDashboard'])
             ->name('admin.epidemiologi.map');
        Route::get('api/map-data', [App\Http\Controllers\EpidemiologiController::class, 'getMapData'])
             ->name('admin.epidemiologi.mapData');
        Route::get('api/dashboard-data', [App\Http\Controllers\EpidemiologiController::class, 'getDashboardData'])
             ->name('admin.epidemiologi.dashboardData');

        // CRUD Routes
        Route::get('/', [App\Http\Controllers\EpidemiologiController::class, 'index'])
             ->name('admin.epidemiologi.index');
        Route::get('get-cases', [App\Http\Controllers\EpidemiologiController::class, 'getSurveillanceCases'])
             ->name('admin.epidemiologi.getCases');
        Route::get('create', [App\Http\Controllers\EpidemiologiController::class, 'create'])
             ->name('admin.epidemiologi.create');
        Route::post('store', [App\Http\Controllers\EpidemiologiController::class, 'store'])
             ->name('admin.epidemiologi.store');
        Route::get('show/{id}', [App\Http\Controllers\EpidemiologiController::class, 'show'])
             ->name('admin.epidemiologi.show');
        Route::get('edit/{id}', [App\Http\Controllers\EpidemiologiController::class, 'edit'])
             ->name('admin.epidemiologi.edit');
        Route::put('update/{id}', [App\Http\Controllers\EpidemiologiController::class, 'update'])
             ->name('admin.epidemiologi.update');
        Route::delete('destroy/{id}', [App\Http\Controllers\EpidemiologiController::class, 'destroy'])
             ->name('admin.epidemiologi.destroy');
        Route::get('{id}/foto/{slot}', [App\Http\Controllers\EpidemiologiController::class, 'servePhoto'])
             ->name('admin.epidemiologi.foto')
             ->where('slot', '[12]');

        // AJAX Helpers
        Route::get('get-kelurahan/{id}', [App\Http\Controllers\EpidemiologiController::class, 'getKelurahan'])
             ->name('admin.epidemiologi.getKelurahan');
        Route::get('get-rt/{id}', [App\Http\Controllers\EpidemiologiController::class, 'getRt'])
             ->name('admin.epidemiologi.getRt');
        Route::get('lookup-nik/{nik}', [App\Http\Controllers\EpidemiologiController::class, 'lookupNik'])
             ->name('admin.epidemiologi.lookupNik');

        // Lokasi Penularan
        Route::get('api/lokasi-penularan', [App\Http\Controllers\EpidemiologiController::class, 'getLokasiPenularan'])
             ->name('admin.epidemiologi.getLokasiPenularan');
        Route::post('api/lokasi-penularan', [App\Http\Controllers\EpidemiologiController::class, 'storeLokasiPenularan'])
             ->name('admin.epidemiologi.storeLokasiPenularan');

        // Imports
        Route::post('import-excel', [App\Http\Controllers\EpidemiologiController::class, 'importExcel'])
             ->name('admin.epidemiologi.importExcel');
        Route::post('reimport/{log}', [App\Http\Controllers\EpidemiologiController::class, 'reimportExcel'])
             ->name('admin.epidemiologi.reimport');
        Route::delete('import-log/{log}', [App\Http\Controllers\EpidemiologiController::class, 'destroyImportLog'])
             ->name('admin.epidemiologi.destroyImportLog');
        Route::delete('import-log', [App\Http\Controllers\EpidemiologiController::class, 'destroyAllImportLogs'])
             ->name('admin.epidemiologi.destroyAllImportLogs');
        Route::get('import-status', [App\Http\Controllers\EpidemiologiController::class, 'importStatus'])
             ->name('admin.epidemiologi.importStatus');

        // Exports
        Route::get('export-excel', [App\Http\Controllers\EpidemiologiController::class, 'exportExcel'])
             ->name('admin.epidemiologi.exportExcel');
        Route::get('export-pdf/{id}', [App\Http\Controllers\EpidemiologiController::class, 'exportPdfMR01'])
             ->name('admin.epidemiologi.exportPdf');

        /*------------------------------------------
        PD3I Dashboard (super-admin / Dinas Kesehatan)
        --------------------------------------------*/
        Route::prefix('pd3i-dashboard/')->middleware('module.role:superadmin')->group(function () {
            Route::get('/', [App\Http\Controllers\Pd3iDashboardController::class, 'index'])
                 ->name('admin.pd3i.dashboard');
            Route::get('api/kinerja', [App\Http\Controllers\Pd3iDashboardController::class, 'kinerja'])
                 ->name('admin.pd3i.apiKinerja');
            Route::get('api/demografi', [App\Http\Controllers\Pd3iDashboardController::class, 'demografi'])
                 ->name('admin.pd3i.apiDemografi');
            Route::get('api/tren', [App\Http\Controllers\Pd3iDashboardController::class, 'tren'])
                 ->name('admin.pd3i.apiTren');
            Route::get('api/wilayah', [App\Http\Controllers\Pd3iDashboardController::class, 'wilayah'])
                 ->name('admin.pd3i.apiWilayah');
            Route::post('export-pdf', [App\Http\Controllers\Pd3iDashboardController::class, 'exportPdf'])
                 ->name('admin.pd3i.exportPdf');
            Route::get('export-excel', [App\Http\Controllers\Pd3iDashboardController::class, 'exportExcel'])
                 ->name('admin.pd3i.exportExcel');
        });
    });
});
