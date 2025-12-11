<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;
use Yabacon\Paystack;

class WalletController extends Controller
{
    private $paystack;
    private $secretKey;
    public function __construct()
    {
        $this->secretKey = config('services.paystack.secret_key');
        $this->paystack = new Paystack($this->secretKey);
    }

    public function balance(Request $request)
    {
        // check if user is authenticated
        $user = $request->user();
        // dd($user);

        // check if user owns a wallet
        if (!$user || !$user->wallet) {
            return response()->json([
                'status' => false,
                'error' => 'Wallet not found',
            ], 404);
        }

        // return wallet balance 
        return response()->json([
            'status' => true,
            // 'wallet' => $user->wallet->load('user'),
            'balance' => $user->wallet->balance,
        ], 200);
    }


    public function deposit(Request $request)
    {
        //? validate request
        $request->validate([
            'amount' => 'required|numeric|min:100',
        ]);

        // dd($request->all());

        try {
            // check if user is authenticated
            $user = $request->user();
            // dd($user);

            if (!$user || !$user->wallet) {
                return response()->json([
                    'status' => false,
                    'error' => 'Wallet not found',
                ], 404);
            }

            // deposit pending funds
            $reference  = "DEP_" . Str::upper(bin2hex(random_bytes(5)));
            // dd($reference);

            //? save transaction record
            $user->wallet->transactions()->create([
                'user_id' => $user->id,
                'wallet_id' => $user->wallet->id,
                'type' => 'deposit',
                'amount' => $request->amount,
                'reference' => $reference,
                'status' => 'pending',
                'direction' => 'credit',
            ]);


            // paysatck initialize transaction
            $trx = $this->paystack->transaction->initialize([
                'amount' => $request->amount * 100,
                'email' => $user->email,
                'reference' => $reference,
                'callback_url' => route('wallet.deposit.callback'),
            ]);

            return response()->json([
                'status' => true,
                // 'message' => 'Deposit successful',
                'reference' => $reference,
                'authorization_url' => $trx->data->authorization_url,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'error' => 'Deposit failed: ' . $e->getMessage(),
            ], 500);
        }
    }


    public function depositCallback(Request $request)
    {
        // verify paystack signature
        $signature = $request->header('x-paystack-signature');
        $payload = $request->getContent();

        Log::info('Paystack Webhook Hit', ['payload' => $payload]);
        Log::info('Paystack Webhook Signature', ['signature' => $signature]);



        // handle deposit callback logic
        try {

            if (!hash_equals(hash_hmac('sha512', $payload, $this->secretKey), $signature)) {
                return response()->json([
                    'status' => false,
                    'error' => 'Invalid signature'
                ], 400);
            }

            $event = json_decode($payload, true);

            if ($event['event'] == 'charge.success') {
                // dd("reached here");
                $data = $event['data'];
                $reference = $data['reference'];

                // find the transaction
                $transaction = Transaction::with('wallet')
                    ->where('reference', $reference)
                    ->where('status', 'pending')
                    ->first();

                if (!$transaction) {
                    return response()->json([
                        'status' => false,
                        'error' => 'Transaction not found',
                    ], 404);
                }

                // update wallet balance
                $wallet = $transaction->wallet;
                $wallet->balance += $transaction->amount;
                $wallet->save();

                // update transaction status
                $transaction->status = 'success';
                $transaction->save();

                return response()->json([
                    'status' => true,
                    // 'message' => 'Deposit verified successfully',
                    // 'new_balance' => $wallet->balance,
                ], 200);
            }


            // if failed transaction status is failed
            if ($event['event'] == 'charge.failed') {
                $data = $event['data'];
                $reference = $data['reference'];

                // find the transaction
                $transaction = Transaction::where('reference', $reference)
                    ->where('status', 'pending')
                    ->first();

                if (!$transaction) {
                    return response()->json([
                        'status' => false,
                        'error' => 'Transaction not found',
                    ], 404);
                }

                // update transaction status
                $transaction->status = 'failed';
                $transaction->save();

                return response()->json([
                    'status' => true,
                    // 'message' => 'Deposit marked as failed',
                ], 200);
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'error' => 'Deposit verification failed: ' . $e->getMessage(),
            ], 500);
        }
    }


    public function transfer(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:100',
            'wallet_number' => 'required|string|exists:wallets,wallet_number',
        ]);

        // dd($request->all());


        //? sender
        $sender = $request->user();
        $senderWallet = $sender->wallet;

        if ($senderWallet->balance < $request->amount) {
            return response()->json([
                'status' => false,
                'error' => 'Insufficient funds',
            ], 400);
        }

        $receiverWallet = Wallet::where('wallet_number', $request->wallet_number)
            ->first();


        // retrict transfer to self
        if ($receiverWallet->id === $senderWallet->id) {
            return response()->json([
                'status' => false,
                'error' => 'You cannot transfer to your own wallet',
            ], 400);
        }


        try {
            DB::beginTransaction();
            //? decrement sender balance
            $senderWallet->decrement('balance', $request->amount);

            //? increment receiver balance
            $receiverWallet->increment('balance', $request->amount);

            // sender transaction record
            Transaction::create([
                'user_id' => $sender->id,
                'wallet_id' => $senderWallet->id,
                'amount' => $request->amount,
                'type' => 'transfer',
                'status' => 'success',
                'reference' => "TRF_" . Str::upper(bin2hex(random_bytes(5))),
                'related_wallet_id' => $receiverWallet->id,
                'metadata' => [
                    'to' => $receiverWallet->user_id
                ]
            ]);

            // Receiver transaction
            Transaction::create([
                'user_id' => $receiverWallet->user_id,
                'wallet_id' => $receiverWallet->id,
                'amount' => $request->amount,
                'type' => 'deposit',
                'status' => 'success',
                'reference' => "DEP_" . Str::upper(bin2hex(random_bytes(5))),
                'related_wallet_id' => $senderWallet->id,
                'metadata' => [
                    'from' => $sender->id
                ]
            ]);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Transfer successful',
            ], 200);
        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'status' => false,
                'error' => 'Transfer failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function depositStatus(Request $request, string $ref)
    {
        try {
            $transaction = Transaction::with('wallet')
                ->where('reference', $ref)
                ->first();

            if (!$transaction) {
                return response()->json([
                    'status' => false,
                    'error' => 'Transaction not found',
                ], 404);
            }

            return response()->json([
                // 'status' => true,
                'status' => $transaction->status,
                'balance' => $transaction->wallet->balance,
                "reference" => $transaction->reference,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'error' => 'Verification failed: ' . $e->getMessage(),
            ], 500);
        }
    }


    public function transactionHistory(Request $request)
    {
        $user = $request->user();

        try {
            $transactions = $user
                ->transactions()
                ->latest()
                ->get();

            return response()->json([
                'status' => true,
                'transactions' => $transactions,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'error' => 'Could not fetch transaction history: ' . $e->getMessage(),
            ], 500);
        }
    }
}