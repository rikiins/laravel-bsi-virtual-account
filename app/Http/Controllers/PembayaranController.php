<?php

namespace App\Http\Controllers;

use App\Models\Tagihan;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PembayaranController extends Controller
{
    private $allowedCollectingAgents = ['BSM'];
    private $allowedChannels = ['TELLER', 'IBANK', 'ATM', 'MBANK', 'FLAGGING'];
    
    public function __construct()
    {
        $this->middleware('AcceptJsonOnly');
    }

    public function index(Request $req)
    {
        $bankRequest = $req->all();
        
        $kodeBank            = $req['kodeBank'];
        $kodeChannel         = $req['kodeChannel'];
        $kodeBiller          = $req['kodeBiller'];
        $kodeTerminal        = $req['kodeTerminal'];
        $nomorPembayaran     = $req['nomorPembayaran'];
        $tanggalTransaksi    = $req['tanggalTransaksi'];
        $idTransaksi         = $req['idTransaksi'];
        $idTagihan           = $req['idTagihan']   ;
        $totalNominal        = $req['totalNominal'];
        $nomorJurnal         = $req['nomorJurnalPembukuan'];
        $checksum            = $req['checksum'];

        if (
            empty($kodeBank) || empty($kodeBiller) || empty($kodeChannel) || empty($kodeTerminal) || 
            empty($nomorPembayaran) || empty($tanggalTransaksi) || empty($idTransaksi) || 
            empty($totalNominal) || empty($nomorJurnal) || empty($checksum) || empty($idTagihan)
        )
        {
            return response()->json([
                'rc'      => 'ERR-PARSING-MESSAGE',
                'message' => 'Invalid message format'
            ], 406);
            
            exit;
        }

        if (!in_array($kodeBank, $this->allowedCollectingAgents))
        {
            $res = [
                'rc'      => 'ERR-BANK-UNKNOWN',
                'message' => 'Collecting agent is not allowed by ' . env('BSI_BILLER_NAME')
            ];
            
            Log::error([
                'bankRequest' => $bankRequest,
                'response'    => $res
            ]);

            return response()->json($res, 406);
            exit;
        }

        if (!in_array($kodeChannel, $this->allowedChannels))
        {
            $res = [
                'rc'      => 'ERR-BANK-UNKNOWN',
                'message' => 'Channel is not allowed by ' . env('BSI_BILLER_NAME')
            ];
            
            Log::error([
                'bankRequest' => $bankRequest,
                'response'    => $res
            ]);

            return response()->json($res, 406);
            exit;
        }

        if (sha1($nomorPembayaran . env('BSI_SECRET_KEY') . $tanggalTransaksi . $totalNominal . $nomorJurnal) != $checksum)
        {
            return response()->json([
                'rc'      => 'ERR-SECURE-HASH',
                'message' => 'H2H Checksum is invalid'
            ], 406);
            
            exit;
        }

        $dataTagihan = Tagihan::where('nomor_siswa', $nomorPembayaran)->orderBy('tanggal_invoice', 'DESC')->first();

        if (empty($dataTagihan['nama']))
        {
            $res = [
                'rc'      => 'ERR-NOT-FOUND',
                'message' => 'Billing number not found'
            ];
            
            Log::error([
                'bankRequest' => $bankRequest,
                'response'    => $res
            ]);

            return response()->json($res, 404);
            exit;
        }

        $dataTagihan = Tagihan::where('nomor_siswa', $nomorPembayaran)->whereNull('status_pembayaran')->orderBy('tanggal_invoice', 'DESC')->first();

        if (empty($dataTagihan['nama']))
        {
            return response()->json([
                'rc' => 'ERR-ALREADY-PAID',
                'message' => 'Bill already paid'
            ]);
            
            exit;
        }

        $dataInquiry = [
            'rc' => 'OK',
            'msg' => 'Inquiry Success',
            'nomorPembayaran' => $nomorPembayaran,
            'idPelanggan' => $nomorPembayaran,
            'nama' => $dataTagihan->nama,
            'totalNominal' => $dataTagihan->nominal_tagihan,
            'informasi' => [
                ['label_key' => 'info1', 'label_value' => $dataTagihan->informasi],
                ['label_key' => 'info2', 'label_value' => $dataTagihan->tanggal_invoice] 
            ],
            'rincian' => [
                [
                    'kode_rincian' => 'TAGIHAN',
                    'deskripsi' => 'TAGIHAN SPP',
                    'nominal' => $dataTagihan->nominal_tagihan
                ]
            ],
            'idTagihan' => $dataTagihan->id_invoice
        ];

        if ($dataTagihan['nominal_tagihan'] != $totalNominal)
        {
            return response()->json([
                'rc' => 'ERR-WRONG-PAYMENT-AMOUNT',
                'message' => "Total paid amount ($totalNominal) is not equal to bill amount ({$dataTagihan['nominal_tagihan']})"
            ]);

            exit;
        }

        try 
        {
            // DB::transaction() sudah memiliki fitur auto-rollback, untuk itu tidak diperlukan lagi
            // me-rollback manual di blok catch.            
            DB::transaction(function() use ($dataTagihan, $nomorJurnal, $kodeChannel) {
                DB::table('tagihan_pembayaran')
                ->where('id_invoice', $dataTagihan->id_invoice)
                ->update([
                    'status_pembayaran' => 'SUKSES',
                    'nomor_jurnal_pembukuan' => $nomorJurnal,
                    'waktu_transaksi' => date('Y-m-d H:i:s'),
                    'channel_pembayaran' => $kodeChannel
                ]);
            });
        } 
        catch (Exception $ex) 
        {
            $res = [
                'rc' => 'ERR-DB',
                'message' => 'Error encountered while updating transaction',
                'reason' => $ex->getMessage()
            ];

            Log::error([
                'bankRequest' => $bankRequest,
                'response'    => $res
            ]);
        }

        $dataInquiry['message'] = 'Payment Success';

        return response()->json($dataInquiry);
    }
    
}
