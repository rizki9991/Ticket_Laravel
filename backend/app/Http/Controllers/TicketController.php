<?php

namespace App\Http\Controllers;

use App\Http\Requests\TicketReplyStoreRequest;
use App\Http\Requests\TicketStoreRequest;
use App\Http\Resources\TicketReplyResource;
use App\Models\Ticket;
use App\Http\Resources\TicketResource;
use App\Models\TicketReply;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketController extends Controller
{

    public function index(Request $request)
    {
        try {
            $query = Ticket::query();

            $query->orderBy('created_at', 'desc');

            if ($request->search) {
                $query->where('code', 'like', '%'. $request->search. '%')
                    ->orWhere('title', 'like', '%'. $request->search. '%');
            }

            if ($request->status) {
                $query->where('status', $request->status);
            }

            if ($request->priority) {
                $query->where('priority', $request->priority);
            }

            if (auth()->user()->role == 'user') {
                $query->where('user_id', auth()->user()->id);
            }

            $ticket = $query->get();

            return response()->json([
                'message' => 'Data Tiket Berhasil Ditampilkan',
                'data' => TicketResource::collection($ticket)
            ],200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi Kesalahan',
                'error' => $e->getMessage(), 
                'data' => null
            ],500);
        }
    }

    public function show($code)
    {
        try {
            $ticket = Ticket::where('code', $code)->first();
            if (!$ticket) {
                return response()->json([
                    'message' => 'Ticket Tidak Ditemukan'
                ], 404);
            }

            if (auth()->user()->role == 'user' && $ticket-> user_id != auth()->user()->id) {
                return response()->json([
                    'message' => 'Anda Tidak DAPAT Menggakses Tiket Ini'
                ], 403);
            }

            return response()->json([
                'message' => 'Tiket Berhasil Ditampilkan',
                'data' => new TicketResource($ticket)
            ],200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi Kesalahan',
                'error' => $e->getMessage(), 
                'data' => null
            ],500);
        }
    }

    public function store(TicketStoreRequest $request)
    {
        $data = $request->validated();
        
        DB::beginTransaction();

        try {
            $ticket = new Ticket;
            $ticket->user_id = auth()->user()->id;
            $ticket->code = 'TIC-'. rand(10000, 99999);
            $ticket->title = $data['title'];
            $ticket->description = $data['description'];
            $ticket->priority = $data['priority'];
            $ticket->save();

            DB::commit();

            return response()->json([
                'message' => 'Ticket Berhasil Ditambahkan',
                'data' => new TicketResource($ticket)
            ],201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Terjadi Kesalahan',
                'error' => $e->getMessage(), 
                'data' => null
            ],500);
        }
    }

    public function storeReply(TicketReplyStoreRequest $request, $code)
    {
        $data = $request->validated();

        DB::beginTransaction();

        try {
            $ticket = Ticket::where('code', $code)->first();
            if (!$ticket) {
                return response()->json([
                        'message' => 'Ticket Tidak Ditemukan'
                ],404);
            }

            if (auth()->user()->role == 'user' && $ticket-> user_id != auth()->user()->id) {
                return response()->json([
                    'message' => 'Anda Tidak DAPAT Menggakses Tiket Ini'
                ], 403);
            }

            $ticketReply = new TicketReply();
            $ticketReply->ticket_id = $ticket->id;
            $ticketReply->user_id = auth()->user()->id;
            $ticketReply->content = $data['content'];
            $ticketReply->save();

            if (auth()->user()->role == 'admin') {
                $ticket->status = $data['status'];
                if ($data['status'] == 'resolved') {
                    $ticket->completed_at = now();
                }
                $ticket->save();
            }

            DB::commit();

            return response()->json([
                'message' => 'Balasan Berhasil Ditambahkan',
                'data' => new TicketReplyResource($ticketReply)
            ],201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Terjadi Kesalahan',
                'error' => $e->getMessage(), 
                'data' => null
            ],500);
        }
    }
}
