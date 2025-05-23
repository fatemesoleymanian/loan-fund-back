<?php

namespace App\Http\Controllers;

use App\Http\Requests\TransactionRequest;
use App\Models\Account;
use App\Models\FundAccount;
use App\Models\Installment;
use App\Models\LoanAccount;
use App\Models\Transaction;
use Hekmatinasser\Verta\Verta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    protected $smsController;

    public function __construct(){
        $this->smsController = new SMSController();
    }
    public function create(TransactionRequest $request)
    {
        DB::beginTransaction();

        try {
            // Step 1: Validate the request
            $validated = $request->validated();

            // Step 2: Retrieve related entities
            $account = $this->getAccount($validated['account_id']);
            $fundAccount = $this->getFundAccount($validated['fund_account_id']);

            // Step 3: Perform transaction-specific logic
            $this->handleTransactionType($validated['type'], $account, $fundAccount, $validated['amount'],$validated['installment_id'] ?? null,$validated['loan_id'] ?? null);
//
//            // Step 4: Save the transaction
            $transaction = $this->createTransaction($validated);
//
//            // Step 5: Commit the transaction
            DB::commit();

            $type = $this->transactionTypeForSmsTemplate($validated['type']);
            $sms = null;

            if($account->have_sms) {
                if ($type === 'installment') $sms = $this->sendSms($validated['installment_id'], $account->member_name, null, $account->member->mobile_number, $type);
                if ($type != null) $sms = $this->sendSms($validated['amount'], $account->member_name, $account->balance, $account->member->mobile_number, $type);
            }
            return $this->successResponse($sms != null ?'تراکنش جدیدی با موفقیت اضافه شد. پیامک ارسال شد.':'تراکنش جدیدی با موفقیت اضافه شد.', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('خطایی در انجام تراکنش رخ داد! ' . $e->getMessage());
        }
    }

    private function getAccount($accountId)
    {
        return Account::with('member')->findOrFail($accountId);
    }

    private function getFundAccount($fundAccountId)
    {
        return FundAccount::findOrFail($fundAccountId);
    }

    private function handleTransactionType($type, $account, $fundAccount, $amount,$installment_id=null,$loan=null)
    {
        switch ($type) {
            case Transaction::TYPE_INSTALLMENT:
                try {
                    $this->updateMinusBalances($account, $fundAccount, $amount,$installment_id);
                } catch (\Exception $e) {
                    throw new \Exception('Invalid transaction type.'.$e->getMessage());
                }
                break;
            case Transaction::TYPE_MONTHLY_PAYMENT:
            case Transaction::TYPE_PENALTY:
            case Transaction::TYPE_FEE:
            case Transaction::TYPE_WITHDRAW:
            try {
                $this->updateMinusBalances($account, $fundAccount, $amount);
            } catch (\Exception $e) {
                throw new \Exception('Invalid transaction type.'.$e->getMessage());
            }
            break;
            case Transaction::TYPE_LOAN_PAYMENT:
                try {
                    $this->updatePlusBalances($account, $fundAccount, $amount,$loan);
                } catch (\Exception $e) {
                    throw new \Exception('نوع تراکنش نامعتبذ است.'.$e->getMessage());
                }
                break;
            case Transaction::TYPE_DEPOSIT:
                try {
                    $this->updatePlusBalances($account, $fundAccount, $amount);
                } catch (\Exception $e) {
                    throw new \Exception('نوع تراکنش نامعتبذ است.'.$e->getMessage());
                }
                break;
            default:
                throw new \Exception('نوع تراکنش نامعتبذ است.');
        }
    }

//az hesabe member kam mishe
    private function updateMinusBalances($account, $fundAccount, $amount,$installment_id=null)
    {
        if ($installment_id != null){
            $loan_id = Installment::where('id',$installment_id)->value('loan_id');
            $payment = LoanAccount::where('loan_id',$loan_id)->where('account_id',$account->id)->first();
             if (!$payment) {
            throw new \Exception("No payment record found for loan and account.".$loan_id);
        }
            $payment->paid_amount += (int)$amount;
            $payment->remained_amount -= (int)$amount;
            $payment->save();
        }
        $account->balance -= (int)$amount;
        $fundAccount->balance += (int)$amount;

        $minimumBalance = 10000; // Example minimum balance
        if ($account->balance < $minimumBalance) {
            throw new \Exception('حداقل موجودی حساب!.');
        }

        $account->save();
        $fundAccount->save();
    }
//az hesabe fund kam mishe
    private function updatePlusBalances($account, $fundAccount, $amount,$pay_loan=null)
    {
        if ($pay_loan != null){
           $loan = LoanAccount::where('account_id',$account->id)->where('loan_id',$pay_loan)->first();
           $loan->paid_by_fund = true;
           $loan->save();
        }
        $fundAccount->balance -= (int)$amount;
        $account->balance += (int)$amount;

        $minimumBalance = 10000; // Example minimum balance
        if ($fundAccount->balance < $minimumBalance) {
            throw new \Exception('حداقل موجودی حساب صندوق!.');
        }

        $account->save();
        $fundAccount->save();
    }

    private function createTransaction($data)
    {
        return Transaction::create([
            'account_id' => $data['account_id'],
            'amount' => $data['amount'],
            'type' => $data['type'],
            'description' => $data['description'],
            'delay_days' => $data['delay_days'] ?? 0,
            'fund_account_id' => $data['fund_account_id'],
            'monthly_charge_id' => $data['monthly_charge_id'] ?? null,
            'installment_id' => $data['installment_id'] ?? null,
        ]);
    }

    public static function successResponse($message, $statusCode)
    {
        return response()->json([
            'msg' => $message,
            'success' => true,
        ], $statusCode);
    }

    public static function errorResponse($message)
    {
        return response()->json([
            'msg' => $message,
            'success' => false,
        ], 500);
    }
////////////////////////////////////////////////////////////////////////////////

    // public function create(TransactionRequest $request){
    //     $request->validated();
    //     $transaction = Transaction::create([
    //         'account_id' => $request->account_id,
    //         'amount' => $request->amount,
    //         'type' => $request->type,
    //         'description' => $request->description,
    //         'delay_days' => $request->delay_days,
    //         'fund_account_id' => $request->fund_account_id,
    //         'monthly_charge_id' => $request->monthly_charge_id,
    //         'installment_id' => $request->installment_id,
    //     ]);
    //     if ($transaction) return response()->json([
    //         'msg' => 'تراکنش جدیدی با موفقیت اضافه شد. .',
    //         'success' => true
    //     ],201);
    //     else return response()->json([
    //         'msg' => 'خطایی در انجام تراکنش رخ داد!',
    //         'success' => false
    //     ],500);
    // }
    public function update(TransactionRequest $request){
        $request->validated();
        $transaction = Transaction::where('id',$request->id)->update([
            'account_id' => $request->account_id,
            'amount' => $request->amount,
            'previous_amount' => $request->previous_amount,
            'type' => $request->type,
            'description' => $request->description,
            'delay_days' => $request->delay_days,
            'fund_account_id' => $request->fund_account_id,
            'monthly_charge_id' => $request->monthly_charge_id,
            'installment_id' => $request->installment_id,
        ]);
        if ($transaction) return response()->json([
            'msg' => 'تراکنش با موفقیت آپدیت شد.',
            'success' => true
        ],201);
        else return response()->json([
            'msg' => 'خطایی در آپدیت تراکنش رخ داد!',
            'success' => false
        ],500);
    }
    public function destroy(Request $request){
        $transaction = Transaction::where('id', $request->id)->delete();
        return response()->json([
            'msg' => 'تراکنش با موفقیت حذف گردید.',
            'success' => true
        ]);
    }
    public function showOne($id){
        $transaction = Transaction::with(['monthlyCharge','installment','account','fundAccount'])->where('id', $id)->first();
        if ($transaction) return response()->json([
            'transaction' => $transaction,
            'success' => true
        ]);
        else return response()->json([
            'msg' => 'خطا در پیدا کردن تراکنش',
            'success' => false
        ]);
    }
    public function showAllByAccount(Request $request){
        $transactions = Transaction::where('account_id',$request->account_id)->with(['monthlyCharge','installment','account','fundAccount'])->get();
        return response()->json([
            'transactions' => $transactions,
            'success' => true
        ]);
    }
    public function showAll(): \Illuminate\Http\JsonResponse
    {
//        $transactions = Transaction::with(['monthlyCharge','installment','account','fundAccount'])->orderByDesc('id')->get();
        $transactions = Transaction::orderByDesc('id')->get();
        $amounts = Transaction::sum('amount');
        return response()->json([
            'amounts' => $amounts,
            'transactions' => $transactions,
            'success' => true
        ]);
    }
    public function showByAccAndCharge($acc_id,$charg_id){
        $transactions = Transaction::with(['fundAccount'])->where('account_id',$acc_id)->where('monthly_charge_id',$charg_id)->get();
        return response()->json([
            'transactions' => $transactions,
            'success' => true
        ]);
    }
    public function showAccInstallmentsByLoan($acc_id,$loan_id){
        $instalments = Installment::where('loan_id',$loan_id)->select('id')->get();
        $transactions = Transaction::with(['fundAccount'])->where('account_id',$acc_id)
            ->whereIn('installment_id',$instalments)->get();
        return response()->json([
            'transactions' => $transactions,
            'success' => true
        ]);
}
    public function showAllByType(Request $request){
        $transactions = Transaction::where('type',$request->type)->with(['monthlyCharge','installment','account','fundAccount'])->get();
        return response()->json([
            'transactions' => $transactions,
            'success' => true
        ]);
    }
//    public function search(Request $request)
//    {
//        // Start the query builder for the Transaction model
//        $query = Transaction::query();
//
//        // Apply conditions based on the provided request parameters
//        if ($request->filled('account_id')) {
//            $query->where('account_id', $request->account_id);
//        }
//
//        if ($request->filled('amount')) {
//            $query->where('amount', $request->amount);
//        }
//
//        if ($request->filled('type')) {
//            $query->where('type', $request->type);
//        }
//
//        if ($request->filled('description')) {
//            $query->where('description', 'like', '%' . $request->description . '%');
//        }
//
//        if ($request->filled('delay_days')) {
//            $query->where('delay_days', $request->delay_days);
//        }
//
//        if ($request->filled('fund_account_id')) {
//            $query->where('fund_account_id', $request->fund_account_id);
//        }
//
//        if ($request->filled('monthly_charge_id')) {
//            $query->where('monthly_charge_id', $request->monthly_charge_id);
//        }
//
//        if ($request->filled('installment_id')) {
//            $query->where('installment_id', $request->installment_id);
//        }
//
//        // Date range filtering (optional)
//        if ($request->filled('start_date') && $request->filled('end_date')) {
//            $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
//        } elseif ($request->filled('start_date')) {
//            $query->whereDate('created_at', '>=', $request->start_date);
//        } elseif ($request->filled('end_date')) {
//            $query->whereDate('created_at', '<=', $request->end_date);
//        }
//
//        // Execute the query with eager loading
////        $transactions = $query->with(['monthlyCharge', 'installment', 'account', 'fundAccount'])->get();
//        $transactions = $query->with(['monthlyCharge','installment','account','fundAccount'])->orderByDesc('id')->get();
//
//        return response()->json([
//            'transactions' => $transactions,
//            'success' => true
//        ]);
//    }
    public function search(Request $request){
        $startSolarDate = $request->query('from');
        $endSolarDate = $request->query('to');

        $query = Transaction::query();
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
            'transactions' => $transactions,
            'success' => true
        ]);
    }
    private function sendSms($amount , $account_name, $balance, $mobile_number,$template){
        $amount = number_format((int)$amount);
        if($template !== 'installment') $balance = number_format((int)$balance);
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
    private function transactionTypeForSmsTemplate($type){
        switch ($type) {
            case Transaction::TYPE_INSTALLMENT:
               return 'installment';
            case Transaction::TYPE_MONTHLY_PAYMENT:
                return 'charge';
            case Transaction::TYPE_PENALTY:
                return 'penalty';
            case Transaction::TYPE_FEE:
                return 'fee';
            case Transaction::TYPE_WITHDRAW:
                return 'withdraw';
            case Transaction::TYPE_LOAN_PAYMENT:
                return 'loan';
            case Transaction::TYPE_DEPOSIT:
                return 'deposit';
            default:
                throw new \Exception('نوع تراکنش نامعتبذ است.');
        }
    }
}
