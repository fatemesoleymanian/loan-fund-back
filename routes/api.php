<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\CharityController;
use App\Http\Controllers\DatabaseBackup;
use App\Http\Controllers\DepositController;
use App\Http\Controllers\FundAccountController;
use App\Http\Controllers\InstallmentController;
use App\Http\Controllers\LoanAccountController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\MonthlyChargeController;
use App\Http\Controllers\MonthlyChargeAccountController;
use App\Http\Controllers\SMSController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\WithdrawController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/


Route::post('/login',function (Request $request){
    if ($request->username == env('USER_NAME') && $request->password == env('PASSWORD')) {
        $sync = InstallmentController::updateDelayDays();
        return response()->json([
            'token' => env('ACCESS_TOKEN'),
            'sync' => $sync
        ], 201);
    }else return response()->json([
        'token' => "نام کاربری یا رمزعبور صحیح نیست!",
    ], 400);
});
Route::middleware(['ip.whitelist','token.auth'])->group(function (){
Route::get('/test',function (){
    echo 'hii';
});
Route::prefix('/fund_account')->group(function (){
    Route::get('/',[FundAccountController::class,'showAll']);
    Route::get('/{id}',[FundAccountController::class,'showOne']);
    Route::post('/',[FundAccountController::class,'create']);
    Route::put('/',[FundAccountController::class,'update']);
    Route::post('/delete',[FundAccountController::class,'destroy']);
});
Route::prefix('/asset')->group(function (){
    Route::get('/',[AssetController::class,'showAll']);
    Route::get('/{id}',[AssetController::class,'showOne']);
    Route::post('/',[AssetController::class,'create']);
//    Route::put('/',[AssetController::class,'update']);
    Route::post('/delete',[AssetController::class,'destroy']);
});
Route::prefix('/charity')->group(function (){
    Route::get('/',[CharityController::class,'showAll']);
    Route::get('/{id}',[CharityController::class,'showOne']);
    Route::post('/',[CharityController::class,'create']);
//    Route::put('/',[CharityController::class,'update']);
    Route::post('/delete',[CharityController::class,'destroy']);
});
Route::prefix('/member')->group(function (){
    Route::get('/',[MemberController::class,'showAll']);
    Route::get('/list',[MemberController::class,'membersList']);
    Route::get('/search={str}',[MemberController::class,'search']);
    Route::get('/{id}',[MemberController::class,'showOne']);
//    Route::post('/',[MemberController::class,'create']);
    Route::put('/',[MemberController::class,'update']);
});
Route::prefix('/account')->group(function (){
    Route::get('/',[AccountController::class,'showAllOpened']);
    Route::get('/all',[AccountController::class,'showAll']);
    Route::get('/list',[AccountController::class,'showList']);
    Route::get('/search',[AccountController::class,'search']);
    Route::get('/{id}',[AccountController::class,'showOne']);
    Route::get('/monthly_charge/{id}',[AccountController::class,'showOneWithMonthlyCharge']);
    Route::get('/loan/{id}',[AccountController::class,'showOneWithLoan']);
    Route::post('/',[AccountController::class,'createMemberAndAccount']);
    Route::put('/',[AccountController::class,'update']);
    Route::put('/settlement',[AccountController::class,'settlement']);
    Route::put('/closure',[AccountController::class,'close']);
    Route::put('/activate',[AccountController::class,'activate']);
});
Route::prefix('/deposit')->group(function () {
    Route::post('/', [DepositController::class, 'create']);
    Route::get('/', [DepositController::class, 'showAll']);
    Route::get('/search', [DepositController::class, 'search']);
    Route::get('/account/latest/{id}', [DepositController::class, 'showLatestDepositsForAccount']);
});
Route::prefix('/withdraw')->group(function () {
    Route::post('/', [WithdrawController::class, 'create']);
    Route::put('/closure', [WithdrawController::class, 'closure']);
    Route::get('/', [WithdrawController::class, 'showAll']);
    Route::get('/search', [WithdrawController::class, 'search']);
    Route::get('/account/latest/{id}', [WithdrawController::class, 'showLatestWithdrawsForAccount']);
});
Route::prefix('/loan')->group(function (){
    Route::get('/',[LoanController::class,'showAll']);
//    Route::get('/{id}',[LoanController::class,'showOne']);
//    Route::get('/with_inst/{loan_id}',[LoanController::class,'showOneWithInst']);
    Route::post('/',[LoanController::class,'create']);
//    Route::put('/',[LoanController::class,'update']);
//    Route::post('/delete',[LoanController::class,'destroy']);
});
Route::prefix('/installment')->group(function (){
    Route::get('/',[InstallmentController::class,'showAll']);
    Route::get('/search',[InstallmentController::class,'search']);
    Route::get('/fee_report',[InstallmentController::class,'showFees']);
    Route::get('/count/{account_id}',[InstallmentController::class,'numberOfUnpaidInstallmentsOfAccount']);
    Route::post('/pay',[InstallmentController::class,'pay']);
    Route::post('/edit_installment',[InstallmentController::class,'editInstallment']);
    Route::get('/latency_sms{type}',[InstallmentController::class,'sendLatencySms']);
    Route::get('/reminder_sms',[InstallmentController::class,'sendReminderSms']);
});
Route::prefix('/loan_account')->group(function (){
    Route::get('/',[LoanAccountController::class,'showAll']);
    Route::get('/search',[LoanAccountController::class,'search']);
    Route::get('/{account_id}',[LoanAccountController::class,'showLoansOfAccount']);
    Route::post('/',[LoanAccountController::class,'createLoanAndPartition']);
    Route::post('/paid_loan',[LoanAccountController::class,'createPaidLoanAndWithoutPartition']);
    Route::post('/delete',[LoanAccountController::class,'destroy']);
});
Route::prefix('/monthly_charge')->group(function (){
    Route::get('/',[MonthlyChargeController::class,'showAll']);
    Route::post('/',[MonthlyChargeController::class,'create']);
    Route::put('/',[MonthlyChargeController::class,'update']);
    Route::post('/delete',[MonthlyChargeController::class,'destroy']);
    Route::post('/check_before_apply',[MonthlyChargeController::class,'checkBeforeApply']);
    Route::post('/apply_to_accounts',[MonthlyChargeController::class,'applyChargeForAccounts']);
});
Route::prefix('/transaction')->group(function (){
        Route::get('/',[TransactionController::class,'showAll']);
        Route::get('/account',[TransactionController::class,'showAllByAccount']);
        Route::get('/type',[TransactionController::class,'showAllByType']);
        Route::get('/acc/{acc_id}/chrg/{charg_id}',[TransactionController::class,'showByAccAndCharge']);
        Route::get('/acc/{acc_id}/loan_inst/{loan_id}',[TransactionController::class,'showAccInstallmentsByLoan']);
        Route::get('/search',[TransactionController::class,'search']);
        Route::get('/{id}',[TransactionController::class,'showOne']);
        Route::post('/',[TransactionController::class,'create']);
        Route::put('/',[TransactionController::class,'update']);
        Route::delete('/',[TransactionController::class,'destroy']);
    });
Route::post('sms/custom_message',[SMSController::class,'sendBulkSmsWithCustomizeText']);






});
