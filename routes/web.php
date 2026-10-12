<?php

use App\Http\Controllers\Account\DepositRequestController;
use App\Http\Controllers\Account\InvestmentController;
use App\Http\Controllers\Account\WithdrawalRequestController;
use App\Http\Controllers\Admin\DepositRequestManagementController;
use App\Http\Controllers\Admin\InvestorAccountManagementController;
use App\Http\Controllers\Admin\InvestmentManagementController;
use App\Http\Controllers\Admin\MembershipChargeManagementController;
use App\Http\Controllers\Admin\PasswordController;
use App\Http\Controllers\Admin\ProfitDistributionManagementController;
use App\Http\Controllers\Admin\ProjectManagementController;
use App\Http\Controllers\Admin\ReferralManagementController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\WithdrawalManagementController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\PasswordResetController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
Route::get('/projects/{project:slug}', [ProjectController::class, 'show'])->name('projects.show');
Route::post('/language', [LocaleController::class, 'update'])->name('language.update');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.store');
    Route::get('/admin/login', [AuthController::class, 'showAdminLogin'])->name('admin.login');
    Route::post('/admin/login', [AuthController::class, 'adminLogin'])->middleware('throttle:10,1')->name('admin.login.store');
    Route::get('/register', [AuthController::class, 'showRegistration'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1')->name('register.store');
    Route::get('/forgot-password', [PasswordResetController::class, 'requestLink'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])->middleware('throttle:6,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:6,1')->name('password.update');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'role:investor'])->prefix('account')->name('account.')->group(function (): void {
    Route::get('/', [DashboardController::class, 'investor'])->name('dashboard');
    Route::get('/deposits/create', [DepositRequestController::class, 'create'])->name('deposits.create');
    Route::post('/deposits', [DepositRequestController::class, 'store'])->middleware('throttle:10,1')->name('deposits.store');
    Route::get('/projects/{project:slug}/invest', [InvestmentController::class, 'create'])->name('projects.invest.create');
    Route::post('/projects/{project:slug}/invest', [InvestmentController::class, 'store'])->middleware('throttle:10,1')->name('projects.invest.store');
    Route::get('/withdrawals/create', [WithdrawalRequestController::class, 'create'])->name('withdrawals.create');
    Route::post('/withdrawals', [WithdrawalRequestController::class, 'store'])->middleware('throttle:10,1')->name('withdrawals.store');
});

Route::middleware(['auth', 'role:super_admin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', [DashboardController::class, 'admin'])->name('dashboard');
    Route::get('/investors', [InvestorAccountManagementController::class, 'index'])->name('investors.index');
    Route::get('/investors/{investor}', [InvestorAccountManagementController::class, 'show'])->whereNumber('investor')->name('investors.show');
    Route::put('/investors/{investor}', [InvestorAccountManagementController::class, 'update'])->whereNumber('investor')->name('investors.update');
    Route::patch('/investors/{investor}/status', [InvestorAccountManagementController::class, 'updateStatus'])->whereNumber('investor')->name('investors.status');
    Route::get('/deposits', [DepositRequestManagementController::class, 'index'])->name('deposits.index');
    Route::post('/deposits/{deposit}/review', [DepositRequestManagementController::class, 'review'])->name('deposits.review');
    Route::get('/investments', [InvestmentManagementController::class, 'index'])->name('investments.index');
    Route::post('/investments/{investment}/review', [InvestmentManagementController::class, 'review'])->name('investments.review');
    Route::get('/withdrawals', [WithdrawalManagementController::class, 'index'])->name('withdrawals.index');
    Route::post('/withdrawals/{withdrawal}/review', [WithdrawalManagementController::class, 'review'])->name('withdrawals.review');
    Route::get('/membership-charges', [MembershipChargeManagementController::class, 'index'])->name('membership-charges.index');
    Route::get('/profit-distributions', [ProfitDistributionManagementController::class, 'index'])->name('profit-distributions.index');
    Route::get('/profit-distributions/create', [ProfitDistributionManagementController::class, 'create'])->name('profit-distributions.create');
    Route::post('/profit-distributions', [ProfitDistributionManagementController::class, 'store'])->name('profit-distributions.store');
    Route::post('/profit-distributions/{distribution}/transition', [ProfitDistributionManagementController::class, 'transition'])->name('profit-distributions.transition');
    Route::post('/projects/demo-projects', [ProjectManagementController::class, 'toggleDemoProjects'])->name('projects.demo-projects');
    Route::resource('projects', ProjectManagementController::class)->except(['show', 'destroy']);
    Route::get('/referrals', [ReferralManagementController::class, 'index'])->name('referrals.index');
    Route::post('/referrals/{commission}/credit', [ReferralManagementController::class, 'credit'])->name('referrals.credit');
    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::get('/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('/password', [PasswordController::class, 'update'])->name('password.update');
});
