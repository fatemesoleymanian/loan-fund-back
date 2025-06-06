<?php

namespace App\Http\Controllers;

use App\Http\Requests\InstallmentPaymentRequest;
use App\Http\Requests\InstallmentRequest;
use App\Models\Account;
use App\Models\FundAccount;
use App\Models\Installment;
use App\Models\LoanAccount;
use App\Models\MonthlyCharge;
use App\Models\Transaction;
use Hekmatinasser\Verta\Verta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InstallmentController extends Controller
{
//    public function update(InstallmentRequest $request){
//        $request->validated();
//        $installment = Installment::where('id',$request->id)->update([
//            'loan_id' => $request->loan_id,
//            'inst_number' => $request->inst_number,
//            'amount_due' => $request->amount_due,
//            'due_date' => $request->due_date,
//        ]);
//        if ($installment) return response()->json([
//            'msg' => 'قسط با موفقیت آپدیت شد .',
//            'success' => true
//        ],201);
//        else return response()->json([
//            'msg' => 'خطایی در آپدیت قسط رخ داد!',
//            'success' => false
//        ],500);
//    }
//    public function updateGroup(InstallmentRequest $request){
//        $request->validated();
//
//        $installmentsData = $request->input('installments');
//        $updatedInstallments = [];
//        DB::beginTransaction();
//        try {
//            foreach ($installmentsData as $data) {
//                $installment = Installment::where('id', $data['id'])->update([
//                    'loan_id' => $data['loan_id'],
//                    'inst_number' => $data['inst_number'],
//                    'amount_due' => $data['amount_due'],
//                    'due_date' => $data['due_date'],
//                ]);
//                $updatedInstallments[] = $installment;
//            }
//
//            if (count($updatedInstallments) === count($installmentsData)) {
//                DB::commit();
//                return response()->json([
//                    'msg' => 'تمامی اقساط با موفقیت آپدیت شدند.',
//                    'success' => true,
//                    'data' => $updatedInstallments,
//                ], 201);
//            } else {
//                return response()->json([
//                    'msg' => 'خطایی در آپدیت برخی از اقساط رخ داد!',
//                    'success' => false,
//                ], 500);
//            }
//        }catch (\Throwable $e) {
//            DB::rollback();
//            return response()->json([
//                'msg' => 'خطایی در آپدیت برخی از اقساط رخ داد!',
//                'success' => false,
//            ], 500);
//        }
//    }
//
//    public function showOne($id){
//        $installment = Installment::with(['loan'])->where('id', $id)->first();
//        if ($installment) return response()->json([
//            'installment' => $installment,
//            'success' => true
//        ]);
//        else return response()->json([
//            'msg' => 'خطا در پیدا کردن  قسط',
//            'success' => false
//        ]);
//    }

    protected $smsController;

    public function __construct(){
        $this->smsController = new SMSController();
    }
    public function showAll(){
        $installments = Installment::where('paid_date',null)->orderBy('due_date','asc')->get();
        return response()->json([
            'installments' => $installments,
            'success' => true
        ]);
    }
    public function search(Request $request){
        $id = $request->query('account_id');
        $loan_account_id = $request->query('loan_account_id');
        $account_name = $request->query('account_name');
        $type = $request->query('type');
        $due_date = $request->query('due_date');
        $title = $request->query('title');
        $is_paid = $request->query('is_paid');
        $query = Installment::query();

        if ($id !== null){
            $query->where('account_id', $id);
        }
        if ($loan_account_id !== null){
            $query->where('loan_account_id', $loan_account_id);
        }
        if ($account_name !== null){
            $query->orWhere('account_name', 'LIKE', "%{$account_name}%");
        }
        if ($type !== null){
            $query->where('type', (int)$type);
        }
        if ($due_date !== null){
            // Convert Solar date to Gregorian format
            $gregorian_due_date = Verta::parse($due_date)->DateTime()->format('Y-m-d');
            // Use whereDate for date-only comparison
            $query->whereDate('due_date', '=', $gregorian_due_date);
        }
        if ($title !== null){
            $query->orWhere('title','LIKE', "%{$title}%");
        }
        if ($is_paid !== null){
            $is_paid === 'true' ? $query->where('paid_date','!=',null): $query->where('paid_date',null);
        }

        $installments = $query->get();
        return response()->json([
            'installments' => $installments,
            'success' => true
        ]);
    }
    public function numberOfUnpaidInstallmentsOfAccount($account_id){
        $count = Installment::where('account_id',$account_id)->where('type',2)->where('paid_date',null)->count();
        return response()->json([
            'counts' => $count,
            'success' => true
        ]);
    }
    public static function numberOfUnpaidInstallmentsOfAccountt($account_id){
       return Installment::where('account_id',$account_id)->where('paid_date',null)->count();
    }
    public function showFees(Request $request){
        $startSolarDate = $request->query('from');
        $endSolarDate = $request->query('to');

        $query = Transaction::query()->where('type',Transaction::TYPE_FEE);
        if ($startSolarDate !== null && $endSolarDate !== null) {
            // Convert solar dates to Gregorian
            $startDate = Verta::parse($startSolarDate)->setTime(0, 0, 0)->toCarbon();
            $endDate = Verta::parse($endSolarDate)->setTime(23, 59, 59)->toCarbon();


            $transactions = $query->whereBetween('created_at', [$startDate, $endDate])->get();
            $amounts = $query->whereBetween('created_at', [$startDate, $endDate])->sum('amount');
        }else{
            $transactions = $query->get();
            $amounts = $query->sum('amount');
        }
        return response()->json([
            'amounts'=>$amounts,
            'fees' => $transactions,
            'success' => true
        ]);
    }

//    public function pay(InstallmentPaymentRequest $request){
//        DB::beginTransaction();
//        try {
//            $account = Account::with('member')->where('id',$request->account_id)->first();
//            $installment = Installment::where('id',$request->id)->first();
//            $fund_account = FundAccount::where('id',$request->fund_account_id)->first();
//
//            if ((int)$request->type == 1) $this->payCharge($request,$account,$installment,$fund_account);
//            else if ((int)$request->type == 2) $this->payLoanInstallment($request,$installment,$fund_account);
//            else return TransactionController::errorResponse('نوع قسط صحیح نیست!',400);
//
//            $transaction = $this->logging($request);
//
//            DB::commit();
//            $account->status = $this->checkForAccountStatus($account->id);
//            $account->save();
//            DB::commit();
//
//            $sms = null;
//
//            if($account->have_sms) {
//                if ((int)$request->type == 1) $sms = $this->sendSms($request->amount, $account->member_name, $account->balance, $account->member->mobile_number, 'charge');
//                else if ((int)$request->type == 2) $sms = $this->sendSms($installment->inst_number, $account->member_name, $request->amount, $account->member->mobile_number, 'installment');
//            }
//
//            return response()->json([
//                'msg' => $sms != null ?'پرداخت انجام شد. پیامک ارسال شد.':'پرداخت انجام شد!',
//                'success' => true,
//                'sms' => $sms
//            ]);
//        }catch (\Exception $exception){
//            DB::rollBack();
//            return TransactionController::errorResponse('خطایی در پرداخت قسط رخ داد!',$exception->getMessage());
//        }
//
//    }
    public function editInstallment(InstallmentPaymentRequest $request){
        //$request->amount should be less than installment acutal amount
        $sms = null;
        DB::beginTransaction();
        try {
            $account = Account::with('member')->where('id',$request->account_id)->first();
            $installment = Installment::where('id',$request->id)->first();
            $fund_account = FundAccount::where('id',$request->fund_account_id)->first();

            $msg = '';
            if($installment->paid_date == null){
                if ((int)$request->type == 1) $this->payChargeAndEdit($request,$account,$installment,$fund_account);
                else if ((int)$request->type == 2) $this->payLoanInstallmentAndEdit($request,$installment,$fund_account);
                else return TransactionController::errorResponse('نوع قسط صحیح نیست!',400);

                $transaction = $this->logging($request);

                DB::commit();
                $account->status = $this->checkForAccountStatus($account->id);
                $account->save();
                DB::commit();


                if($account->have_sms) {
                    if ((int)$request->type == 1) $sms = $this->sendSms($request->amount, $account->member_name, $account->balance, $account->member->mobile_number, 'charge');
                    else if ((int)$request->type == 2) $sms = $this->sendSms($installment->inst_number, $account->member_name, $request->amount, $account->member->mobile_number, 'installment');
                    $msg = 'پرداخت انجام شد. پیامک ارسال شد.';
                } else $msg = 'پرداخت انجام شد!';
            }
            else $msg = 'قسط قبلا پرداخت شده!';


            return response()->json([
                'msg' => $msg,
                'success' => true,
                'sms' => $sms
            ]);
        }catch (\Exception $exception){
            DB::rollBack();
//            return TransactionController::errorResponse('خطایی در پرداخت قسط رخ داد!',$exception->getMessage());
            return TransactionController::errorResponse($exception->getMessage());
        }

    }
    private function payChargeAndEdit($request,$account,$installment,$fund_account){
        if ($request->amount < $installment->amount) {
            $fund_account->balance += $request->amount;
            $fund_account->total_balance += $request->amount;
            $fund_account->save();

            $account->balance += $request->amount;
            $account->save();

            $monthly_charge = MonthlyCharge::where('id',$installment->monthly_charge_id)->first();
            $insts = Installment::where('monthly_charge_id',$installment->monthly_charge_id)
                ->where('account_id',$account->id)->count();
            $remaining_amount_for_second_inst = $installment->amount - $request->amount;

            Installment::create([
                'monthly_charge_id' => $installment->monthly_charge_id,
                'year' => $installment->year,
                'account_id' => $installment->account_id,
                'account_name' => $installment->account_name,
                'inst_number' => $insts+1,
                'amount' => (int)$remaining_amount_for_second_inst,
                'due_date' => Verta::parse($request->new_due_date)->format('Y/m/d'),
                'paid_date' => null,
                'delay_days' => 0,
                'type' => 1,
                'title' => $monthly_charge->title
            ]);
            $installment->paid_date = Verta::now();
            $installment->amount = $request->amount;
            $installment->save();
        }else return TransactionController::errorResponse('مبلغ قسط صحیح نیست!',400);
    }
    private function payLoanInstallmentAndEdit($request,$installment,$fund_account){
        if ($request->amount < $installment->amount) {
            $fund_account->balance += $request->amount;
            $fund_account->total_balance += $request->amount;
            $fund_account->save();

            $loan = LoanAccount::where('id',$installment->loan_account_id)->first();
            $loan->paid_amount += $request->amount;
            $loan->no_of_paid_inst += 1;
            $loan->number_of_installments += 1;
            $loan->save();


            $insts = Installment::where('loan_account_id',$installment->loan_account_id)
                ->where('account_id',$installment->account_id)->count();
            $remaining_amount_for_second_inst = $installment->amount - $request->amount;

            Installment::create([
                'loan_id' => $installment->loan_id,
                'loan_account_id' => $installment->loan_account_id,
                'account_id' => $installment->account_id,
                'account_name' => $installment->account_name,
                'inst_number' => $insts+1,
                'amount' => (int)$remaining_amount_for_second_inst,
                'due_date' => Verta::parse($request->new_due_date)->format('Y/m/d'),
                'paid_date' => null,
                'delay_days' => 0,
                'type' => 2,
                'title' => $request->title,
            ]);

            $installment->paid_date = Verta::now();
            $installment->amount = $request->amount;
            $installment->save();
        }else return TransactionController::errorResponse('مبلغ قسط صحیح نیست!',400);
    }
    public function pay(InstallmentPaymentRequest $request){
        $sms = null;
        DB::beginTransaction();
        try {
            $account = Account::with('member')->where('id',$request->account_id)->first();
            $installment = Installment::where('id',$request->id)->first();
            $fund_account = FundAccount::where('id',$request->fund_account_id)->first();

            $msg = '';
            if($installment->paid_date == null){
                if ((int)$request->type == 1) $this->payCharge($request,$account,$installment,$fund_account);
                else if ((int)$request->type == 2) $this->payLoanInstallment($request,$installment,$fund_account);
                else return TransactionController::errorResponse('نوع قسط صحیح نیست!',400);

                $transaction = $this->logging($request);

                DB::commit();
                $account->status = $this->checkForAccountStatus($account->id);
                $account->save();
                DB::commit();


                if($account->have_sms) {
                    if ((int)$request->type == 1) $sms = $this->sendSms($request->amount, $account->member_name, $account->balance, $account->member->mobile_number, 'charge');
                    else if ((int)$request->type == 2) $sms = $this->sendSms($installment->inst_number, $account->member_name, $request->amount, $account->member->mobile_number, 'installment');
                    $msg = 'پرداخت انجام شد. پیامک ارسال شد.';
                } else $msg = 'پرداخت انجام شد!';
            }
            else $msg = 'قسط قبلا پرداخت شده!';


            return response()->json([
                'msg' => $msg,
                'success' => true,
                'sms' => $sms
            ]);
        }catch (\Exception $exception){
            DB::rollBack();
            return TransactionController::errorResponse('خطایی در پرداخت قسط رخ داد!',$exception->getMessage());
        }

    }
    private function payCharge($request,$account,$installment,$fund_account){
        if ($request->amount === $installment->amount) {
            $fund_account->balance += $request->amount;
            $fund_account->total_balance += $request->amount;
            $fund_account->save();

            $account->balance += $request->amount;
            $account->save();

            $installment->paid_date = Verta::now();
            $installment->save();
        }else return TransactionController::errorResponse('مبلغ قسط صحیح نیست!',400);
    }
    private function payLoanInstallment($request,$installment,$fund_account){
        if ($request->amount === $installment->amount) {
            $fund_account->balance += $request->amount;
            $fund_account->total_balance += $request->amount;
            $fund_account->save();

            $loan = LoanAccount::where('id',$installment->loan_account_id)->first();
            $loan->paid_amount += $request->amount;
            $loan->no_of_paid_inst += 1;
            $loan->save();

            $installment->paid_date = Verta::now();
            $installment->save();
        }else return TransactionController::errorResponse('مبلغ قسط صحیح نیست!',400);
    }
    private function checkForAccountStatus($account_id){
       $ownings = Installment::where('account_id',$account_id)->where('paid_date',null)->count();
       if($ownings > 0) return Account::STATUS_DEBTOR;
       else return Account::STATUS_CREDITOR;
}
    private function logging($request){
        $transaction = Transaction::create([
            'account_id' => $request->account_id,
            'loan_account_id' => $request->loan_account_id,
            'amount' => $request->amount,
            'type' => (int)$request->type == 1 ? Transaction::TYPE_MONTHLY_PAYMENT : Transaction::TYPE_INSTALLMENT,
            'description' => (int)$request->type == 1 ? Transaction::TYPE_MONTHLY_PAYMENT : Transaction::TYPE_INSTALLMENT,
            'fund_account_id' => $request->fund_account_id,
            'account_name' => $request->account_name,
            'fund_account_name' => 'صندوق',
        ]);
        return $transaction;
    }

    public static function updateDelayDays()
{
    $today = Verta::now(); // Current date as Verta instance
    $todayFormatted = $today->format('Y/m/d');

    // Fetch installments with due_date and id fields where paid_date is null
    $installments = Installment::whereNull('paid_date')->get(['id', 'due_date']);

    foreach ($installments as $installment) {
        // Parse due_date to Verta instance
        $dueDate = Verta::parse($installment->due_date);

        // Calculate the difference in days
        if($today->greaterThan($dueDate)) $delayDays = $today->diffDays($dueDate); // false to allow negative differences
        else $delayDays = 0;
        // Update delay_days field
        $installment->delay_days = $delayDays;
        $installment->save();
    }

    return response()->json([
        'success' => true
    ]);
}

    private function sendSms($amount , $account_name, $balance, $mobile_number,$template){
        if($template !== 'installment') $amount = number_format((int)$amount);
        $balance = number_format((int)$balance);
        return $this->smsController->
        sendTemplateSms(
            [  'type' => 1,
                'param1' => (string)$amount,
                'param2' => (string)$account_name,
                'param3' => (string)$balance,
                'receptor' => (string)$mobile_number,
                'template' => $template
            ]);

    }
    public function sendLatencySms($type){
        $receptors = [];
        $installments = Installment::where('paid_date',null)
            ->where('delay_days','>','0')->where('type',(int)$type)->get()->groupBy('account_id');

        $accountIds = $installments->keys();
        $receptorsArray = Account::whereHas('member')
        ->with('member')
        ->whereIn('id', $accountIds)->get()->pluck('member.mobile_number')
            ->filter()->unique()->values()->toArray();

        $messagesArray = [];

        foreach ($installments as $accountId => $accountInstallments) {
            $numbers_of_installments = sizeof($accountInstallments);
            $message = (int)$type == 1 ?
             ("باسلام\nحساب شما دارای $numbers_of_installments ماهیانه پرداخت نشده است." .
                "لطفا برای پرداخت اقدام کنید.\n".
                "\nصندوق خانوادگی سلیمانیان (شهید طایف)\nلغو 11") :
                ("باسلام\nحساب شما دارای $numbers_of_installments قسط پرداخت نشده بابت وام شماره ".$accountInstallments[0]->loan_id."می باشد.لطفا برای پرداخت اقدام کنید.\n".
                "\nصندوق خانوادگی سلیمانیان (شهید طایف)\nلغو 11");
            array_push($messagesArray,urlencode($message));
        }


        $receptors = implode(',', $receptorsArray);
        $message = implode(',', $messagesArray);

        return sizeof($receptorsArray) > 0 ? $this->smsController->sendBulkSms([
            'message' =>$message,
            'receptors' => $receptors
        ]) : response()->json([
            'msg' => 'قسط یا ماهیانه پرداخت نشده ای وجود ندارد!',
            'success' => true
        ]);

//        return response()->json([
//            'installments' => $receptors,
//            'success' => $message
//        ]);
    }
    public function sendReminderSms(){
        $todayJalali = Verta::now()->startDay();
        $cutoffJalali = $todayJalali->copy()->addDays(28)->endDay();
        $todayGregorian = $todayJalali->datetime()->format('Y-m-d');
        $cutoffGregorian = $cutoffJalali->datetime()->format('Y-m-d');

        $installments = Installment::whereNull('paid_date')->where('type',2)
            ->whereBetween('due_date', [$todayGregorian, $cutoffGregorian])
            ->get()->groupBy('account_id');

        $accountIds = $installments->keys();
        $receptorsArray = Account::whereHas('member')
            ->with('member')
            ->whereIn('id', $accountIds)->get()->pluck('member.mobile_number')
            ->filter()->unique()->values()->toArray();

        $messagesArray = [];

        $tt = null;
        foreach ($installments as $accountId => $accountInstallments) {
            $numbers_of_installments = sizeof($accountInstallments);
            $message = $numbers_of_installments > 1 ?
                ("باسلام\nتا تاریخ سررسید $numbers_of_installments قسط شما کمتر از یک هفته باقیست.\nصندوق خانوادگی سلیمانیان (شهید طایف)\nلغو 11") :
                ("باسلام\nتا تاریخ سررسید قسط وام شماره ".$accountInstallments[0]->loan_id." کمتر از یک هفته باقیست.\nصندوق خانوادگی سلیمانیان (شهید طایف)\nلغو 11");
            array_push($messagesArray,urlencode($message));
        }


        $receptors = implode(',', $receptorsArray);
        $message = implode(',', $messagesArray);

        return sizeof($receptorsArray) > 0 ? $this->smsController->sendBulkSms([
            'message' =>$message,
            'receptors' => $receptors
        ]) : response()->json([
            'msg' => 'قسط یا ماهیانه پرداخت نشده ای وجود ندارد!',
            'success' => true
        ]);
//                return response()->json([
//            'installments' => $installments,
//            'success' => true
//        ]);

    }
}
