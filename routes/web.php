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

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

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
    Route::get('analytics', [App\Http\Controllers\AdminController::class, 'analyticsDashboard'])->name('admin.analytics');
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
    Route::get('get-posyandu-dasar-anak/{id}', [App\Http\Controllers\AdminController::class, 'getPosyanduAnak'])->name('admin.getPosyanduAnak');
    Route::post('store-data-dasar-anak', [App\Http\Controllers\AdminController::class, 'storeAnak'])->name('admin.storeAnak');
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
    //Data Imunisasi Anak Route List (Legacy - Imunisasi Dasar)
    Route::get('data-imunisasi-anak/{id}', [App\Http\Controllers\AdminController::class, 'dataImunisasi'])->name('admin.dataImunisasi');
    Route::put('update-data-imunisasi-anak/{id}', [App\Http\Controllers\AdminController::class, 'updateImunisasi'])->name('admin.updateImunisasi');
    //Data Imunisasi Lengkap (Enhanced)
    Route::get('imunisasi-lengkap/{id}', [App\Http\Controllers\AdminController::class, 'imunisasiLengkap'])->name('admin.imunisasiLengkap');
    Route::get('jadwal-imunisasi/{id}', [App\Http\Controllers\AdminController::class, 'jadwalImunisasi'])->name('admin.jadwalImunisasi');
    Route::post('store-imunisasi', [App\Http\Controllers\AdminController::class, 'storeImunisasiDetail'])->name('admin.storeImunisasiDetail');
    Route::put('update-imunisasi-detail/{id}', [App\Http\Controllers\AdminController::class, 'updateImunisasiDetail'])->name('admin.updateImunisasiDetail');
    Route::delete('delete-imunisasi/{id}', [App\Http\Controllers\AdminController::class, 'deleteImunisasiDetail'])->name('admin.deleteImunisasiDetail');
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
});

/*------------------------------------------
--------------------------------------------
Claude Dummy Routes (for view testing)
--------------------------------------------
--------------------------------------------*/
Route::prefix('claude/')->group(function () {
    Route::get('dashboard', [App\Http\Controllers\DummyController::class, 'dashboard'])->name('claude.dashboard');

    // Children routes
    Route::get('children', [App\Http\Controllers\DummyController::class, 'childrenIndex'])->name('children.index');
    Route::get('children/{id}', [App\Http\Controllers\DummyController::class, 'showChild'])->name('children.show');
    Route::get('children/{id}/edit', [App\Http\Controllers\DummyController::class, 'editChild'])->name('children.edit');

    // Growth routes
    Route::get('growth', [App\Http\Controllers\DummyController::class, 'growthIndex'])->name('growth.index');
    Route::get('growth/create/{id}', [App\Http\Controllers\DummyController::class, 'createGrowth'])->name('growth.create');

    // Immunizations routes
    Route::get('immunizations', [App\Http\Controllers\DummyController::class, 'immunizationsIndex'])->name('immunizations.index');
    Route::get('immunizations/create/{id}', [App\Http\Controllers\DummyController::class, 'createImmunization'])->name('immunizations.create');

    // Visits routes
    Route::get('visits/create/{id}', [App\Http\Controllers\DummyController::class, 'createVisit'])->name('visits.create');
});
