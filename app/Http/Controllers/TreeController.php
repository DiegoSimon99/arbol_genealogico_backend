<?php

namespace App\Http\Controllers;

use App\Services\TreeService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TreeController extends Controller
{
    public function __construct(private TreeService $tree) {}

    public function store(Request $req) : JsonResponse
    {
        $data = $req->validate([
            'name' => 'required|string',
            'parent_id' => 'nullable|integer|exists:people,id'
        ]);
        $p = $this->tree->createPerson($data['name'], $data['parent_id'] ?? null);
        return response()->json($p);
    }

    public function attach(Request $req) : JsonResponse
    {
        $data = $req->validate([
            'child_id'  => 'required|integer|exists:people,id',
            'parent_id' => 'required|integer|exists:people,id',
        ]);
        try {
            $this->tree->attachAsChild($data['child_id'], $data['parent_id']);
            return response()->json(['ok' => true]);
        } catch (\Throwable $th) {
            return response()->json(['message' => $th->getMessage()],422);
        }
    }

    public function move(Request $req) : JsonResponse
    {
        $data = $req->validate([
            'node_id'      => 'required|integer|exists:people,id',
            'new_parent_id'=> 'required|integer|exists:people,id',
        ]);
        try {
            $this->tree->moveSubtree($data['node_id'], $data['new_parent_id']);
            return response()->json(['ok' => true]);
        } catch (\Throwable $th) {
            return response()->json(['message' => $th->getMessage()],422);
        }
    }

    public function destroy(int $id) : JsonResponse
    {
        try {
            $this->tree->deletePerson($id);
            return response()->json(['ok' => true]);
        } catch (ModelNotFoundException $th) {
            return response()->json(['message' => 'Persona no encontrada.'],404);
        }
    }

    public function bfs(int $rootId) : JsonResponse
    {
        return response()->json($this->tree->bfs($rootId));
    }

    public function dfs(int $rootId) : JsonResponse
    {
        return response()->json($this->tree->dfs($rootId));
    }

    public function show(int $id) : JsonResponse
    {
        $p = $this->tree->findById($id);
        return $p ? response()->json($p) : response()->json(['message'=>'Persona no encontrada'], 404);
    }

    public function maxDepth(int $rootId) : JsonResponse
    {
        return response()->json(['max_depth' => $this->tree->maxDepthFrom($rootId)]);
    }

    public function countDescendants(int $id) : JsonResponse
    {
        return response()->json(['descendants' => $this->tree->countDescendants($id)]);
    }
}
