<?php

namespace App\Http\Controllers;

use App\Models\Tagihan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TagihanController extends Controller
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
        $checksum            = $req['checksum'];

        if (
            empty($kodeBank) || empty($kodeBiller) || empty($kodeChannel) || empty($kodeTerminal) || 
            empty($nomorPembayaran) || empty($tanggalTransaksi) || empty($idTransaksi)
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

        if (sha1($nomorPembayaran . env('BSI_SECRET_KEY') . $tanggalTransaksi) != $checksum)
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
            'rc'              => 'OK',
            'message'         => 'Inquiry Success',
            'idTagihan'       => $dataTagihan->id_invoice,
            'nomorPembayaran' => $nomorPembayaran,
            'idPelanggan'     => $nomorPembayaran,
            'nama'            => $dataTagihan->nama,
            'totalNominal'    => $dataTagihan->nominal_tagihan,
            'informasi' => [
                'info1' => $dataTagihan->informasi
            ]
        ];

        return response()->json($dataInquiry);
    }
}
