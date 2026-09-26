<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\EstimateController;
use App\Http\Controllers\FeeSettingController;
use App\Http\Controllers\GeneralSettingController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VehicleController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout']);
Route::get('/user', [AuthController::class, 'user']);
Route::post('/register', [AuthController::class, 'register']);

Route::get('/users', [UserController::class, 'index']);
Route::post('/users', [UserController::class, 'store']);
Route::put('/users/{user}', [UserController::class, 'update']);

Route::get('/customers', [CustomerController::class, 'index'])->middleware('permission:customers.view');
Route::post('/customers', [CustomerController::class, 'store'])->middleware('permission:customers.create');
Route::put('/customers/{customer}', [CustomerController::class, 'update'])->middleware('permission:customers.edit');
Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->middleware('permission:customers.delete');

Route::get('/vehicles', [VehicleController::class, 'index'])->middleware('permission:vehicles.view');
Route::post('/vehicles', [VehicleController::class, 'store'])->middleware('permission:vehicles.create');
Route::put('/vehicles/{vehicle}', [VehicleController::class, 'update'])->middleware('permission:vehicles.edit');
Route::post('/vehicles/{vehicle}', [VehicleController::class, 'update'])->middleware('permission:vehicles.edit');
Route::delete('/vehicles/{vehicle}', [VehicleController::class, 'destroy'])->middleware('permission:vehicles.delete');

Route::get('/estimates', [EstimateController::class, 'index'])->middleware('permission:estimates.view');
Route::post('/estimates', [EstimateController::class, 'store'])->middleware('permission:estimates.create');
Route::get('/estimates/{estimate}', [EstimateController::class, 'show'])->middleware('permission:estimates.view');
Route::put('/estimates/{estimate}', [EstimateController::class, 'update'])->middleware('permission:estimates.edit');
Route::delete('/estimates/{estimate}', [EstimateController::class, 'destroy'])->middleware('permission:estimates.delete');

Route::get('/roles', [RoleController::class, 'index'])->middleware('permission:roles.view');
Route::post('/roles', [RoleController::class, 'store'])->middleware('permission:roles.create');
Route::put('/roles/{role}', [RoleController::class, 'update'])->middleware('permission:roles.edit');
Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:roles.delete');

Route::get('/permissions', [PermissionController::class, 'index'])->middleware('permission:permissions.view');
Route::post('/permissions', [PermissionController::class, 'store'])->middleware('permission:permissions.create');
Route::put('/permissions/{permission}', [PermissionController::class, 'update'])->middleware('permission:permissions.edit');
Route::delete('/permissions/{permission}', [PermissionController::class, 'destroy'])->middleware('permission:permissions.delete');

Route::get('/products', [ProductController::class, 'index'])->middleware('permission:products.view');
Route::post('/products', [ProductController::class, 'store'])->middleware('permission:products.create');
Route::put('/products/{product}', [ProductController::class, 'update'])->middleware('permission:products.edit');
Route::post('/products/{product}', [ProductController::class, 'update'])->middleware('permission:products.edit');
Route::delete('/products/{product}', [ProductController::class, 'destroy'])->middleware('permission:products.delete');

Route::get('/brands', [BrandController::class, 'index'])->middleware('permission:brands.view');
Route::post('/brands', [BrandController::class, 'store'])->middleware('permission:brands.create');
Route::put('/brands/{brand}', [BrandController::class, 'update'])->middleware('permission:brands.edit');
Route::delete('/brands/{brand}', [BrandController::class, 'destroy'])->middleware('permission:brands.delete');

Route::get('/categories', [CategoryController::class, 'index'])->middleware('permission:categories.view');
Route::post('/categories', [CategoryController::class, 'store'])->middleware('permission:categories.create');
Route::put('/categories/{category}', [CategoryController::class, 'update'])->middleware('permission:categories.edit');
Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->middleware('permission:categories.delete');

Route::get('/settings/general', [GeneralSettingController::class, 'show'])->middleware('permission:general-settings.view');
Route::put('/settings/general', [GeneralSettingController::class, 'update'])->middleware('permission:general-settings.edit');
Route::post('/settings/general', [GeneralSettingController::class, 'update'])->middleware('permission:general-settings.edit');

Route::get('/settings/fees-and-rates', [FeeSettingController::class, 'show'])->middleware('permission:fees-and-rates.view');
Route::put('/settings/fees-and-rates', [FeeSettingController::class, 'update'])->middleware('permission:fees-and-rates.edit');
