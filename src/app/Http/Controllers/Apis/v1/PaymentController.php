<?php

namespace App\Http\Controllers\Apis\v1;

use Illuminate\Support\Facades\Validator;
use App\Models\Payment;
use App\Models\BusinessConsumer;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Traits\HandlesJsonResponse;
use App\Http\Traits\HandleRequest;
use Illuminate\Support\Str;
use DB;
use Carbon\Carbon;


class PaymentController extends Controller{
    /**
     * Create a new controller instance.
     *
     * @return void
     */

    use HandlesJsonResponse, HandleRequest;

    private $foundMessage = 'response.messages.found';
    private $foundMultipleMessage = 'response.messages.found_multiple';
    private $notFoundError = 'response.errors.not_found';
    private $notFoundMessage = 'response.messages.not_found';
    private $addedMessage = 'response.messages.added';
    private $notAddedMessage = 'response.messages.not_added';
    private $updatedMessage = 'response.messages.updated';
    private $deletedMessage = 'response.messages.deleted';
    private $successCode = 'response.codes.success';
    private $errorCode = 'response.codes.error';
    private $notFoundErrorCode = 'response.codes.not_found_error';
    private $consumerAttribute = 'payment';
    private $consumersAttribute = 'payments';
    private $isBoolean = 'boolean';
    private $isRequiredNumeric = 'required|numeric';
    private $isRequiredInteger = 'required|integer';
    private $isNullableInteger = 'nullable|integer';
    private $isNullableString = 'nullable|string|max:255';
    private $isRequiredEmail =  'required|email|max:255';
    private $isRequiredString =  'required|string|max:255';
    private $isUnique = 'unique:payments';
    private $error = 'response.errors.request';
    private $balance = 0;

    public function fetch(Request $request){
        $payment = Payment::all();;
        return $this->jsonResponse(__($this->foundMultipleMessage, ['attr' => $this->consumersAttribute]), __($this->successCode), 200, $payment);
    }

    public function fetchSingle(Request $request, $paymentId){
        
        $payment = Payment::find($paymentId);

        $response = !$payment ? $this->jsonResponse(__($this->notFoundMessage, ['attr' => $this->consumerAttribute]), __($this->notFoundErrorCode), 404, [], __($this->notFoundError))
        : $this->jsonResponse(__($this->foundMessage, ['attr' => $this->consumerAttribute]), __($this->successCode), 200, $payment);

        return $response;
    }

    public function fetchUserPayment(Request $request, $userId){
        $payments = Payment::where('user_id', $userId)->get();
        return $this->jsonResponse(__($this->foundMultipleMessage, ['attr' => $this->consumersAttribute]), __($this->successCode), 200, $payments);
    }
    
    public function store(Request $request){
        $rules = [
            'user_id' =>$this->isRequiredInteger,
            'amount' =>$this->isRequiredNumeric,
            'account_name' => $this->isRequiredString,
            'account_number' => $this->isRequiredString,
            'type' => $this->isRequiredString,
            'description' => $this->isRequiredString,
            'channel' => $this->isRequiredString,
            'bank_code' => $this->isRequiredString,
            'status'=> $this->isRequiredInteger ,
            
        ];

        $validator =  Validator::make($request->all(), $rules);

        if($validator->fails()){
            return $this->jsonValidationError($validator);
        }

        //check if user exist

        //check for negative value
        if($request->amount < 0){
            return $this->jsonResponse(__('Negative amount detected', ['attr' => $this->consumerAttribute]), __($this->notFoundErrorCode), 404, [], __($this->notFoundError));
        }
    
        $reference = time().str_shuffle(time());
        $input = $request->all();
        $input['reference'] = $reference;
        $payment = Payment::create($input);
        switch ($payment->status) {
            case 1:
              $status = 'pending';
              break;
    
            case 2:
              $status = 'successful';
              break;
    
            default:
              $status = 'failed';
              break;
          }

          try {

            $res = $this->call('GET', $request, config('walletws.url').'/api/v1/wallet/user/'.$request->user_id, [
            'content-type' => 'application/json',
            'accept' => 'application/json'
            ]);
            
            $response = json_decode($res->getBody());

            $wallet = $response->data;
            
            $this->balance = $wallet->balance;

          } catch (Throwable $e) {
            Loggable::error($e);
            return $this->jsonResponse($e->getMessage(), __($this->errorCode), 500, [], __('Something went wrong.'));
          }

          //Add successful credit payments to users wallet balance
        if(($request->type == 'credit') && ($request->status == 2)){
            try {
                $request->merge([
                'balance'=>$this->balance + $request->amount,
                ]);

                $res = $this->call('POST', $request, config('walletws.url').'/api/v1/wallet/balance', [
                    'content-type' => 'application/json',
                    'accept' => 'application/json'
                    ]);
              } catch (Throwable $e) {
                Loggable::error($e);
                return $this->jsonResponse($e->getMessage(), __($this->errorCode), 500, [], __('Something went wrong.'));
              }
        }

         //Deduct successful debit payments from users wallet balance
        if(($request->type == 'debit') && ($request->status == 2)){
            try {
                $request->merge([
                'balance'=>$this->balance - $request->amount,
                ]);

                $res = $this->call('POST', $request, config('walletws.url').'/api/v1/wallet/balance', [
                    'content-type' => 'application/json',
                    'accept' => 'application/json'
                    ]);
              } catch (Throwable $e) {
                Loggable::error($e);
                return $this->jsonResponse($e->getMessage(), __($this->errorCode), 500, [], __('Something went wrong.'));
              }
        }
        
        $payment->status = $status;

        return $this->jsonResponse(__($this->addedMessage, ['attr' => $this->consumerAttribute]), __($this->successCode), 201, $payment);
    }

    public function update(Request $request, $paymentId){
        $rules = [
            'user_id' =>$this->isRequiredInteger,
            'email' =>$this->isRequiredEmail,
            'account_name' => $this->isRequiredString,
            'balance'=> $this->isNullableInteger ,
            'lien' => $this->isNullableInteger ,
        ];

        $validator =  Validator::make($request->all(), $rules);

        if($validator->fails()){
            return $this->jsonValidationError($validator);
        }

        $consumer = $this->findConsumer($businessId, $consumerId, $request->is_live);

        if(!$consumer){
            return $this->jsonResponse(__($this->notFoundMessage, ['attr' => $this->consumerAttribute]), __($this->notFoundErrorCode), 404, [], __($this->notFoundError));
        }

        $data = $this->fetchConsumer($businessId, $consumerId, $request->is_live);

        return $this->jsonResponse(__($this->updatedMessage, ['attr' => $this->consumerAttribute]), __($this->successCode), 200, $data);
    }

    public function updateBalance(Request $request){
        $rules = [
          'user_id' => $this->isRequiredInteger,
          'balance' => $this->isRequiredNumeric
        ];
        
        $validator =  Validator::make($request->all(), $rules);
    
        if($validator->fails()){
          return $this->jsonValidationError($validator);
        }
    
        $wallet = Wallet::where('user_id', $request->user_id)->first();
    
        if(!$wallet){
            $this->jsonResponse(__($this->notFoundMessage, ['attr' => $this->consumerAttribute]), __($this->notFoundErrorCode), 404, [], __($this->notFoundError));
        }

        if($request->balance < 0){
             return $this->jsonResponse(__("invalid amount detected please try again with a positive figure", ['attr' => $this->consumerAttribute]), __($this->notFoundErrorCode), 404, [], __($this->notFoundError));
        }
    
        Wallet::where([
          'user_id' => $request->user_id
        ])->update([
    
          'balance' => $request->balance,
        ]);
        
        $wallet = Wallet::where('user_id', $request->user_id)->first();
        
        return $this->jsonResponse(__($this->foundMessage, ['attr' => $this->consumerAttribute]), __($this->successCode), 200, $wallet);
    }

}
