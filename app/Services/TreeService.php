<?php

namespace App\Services;

use App\Models\Person;
use App\Models\PersonClosure;
use Illuminate\Support\Facades\DB;

class TreeService
{
    public function createPerson(string $name, ?int $parentId = null): Person
    {
        return DB::transaction(function () use ($name, $parentId) {
            $person = Person::create(['name' => $name]);

            PersonClosure::create([
                'ancestor_id'   => $person->id,
                'descendant_id' => $person->id,
                'depth'         => 0,
            ]);

            if ($parentId) {
                $this->attachAsChild($person->id, $parentId);
            }

            return $person;
        });
    }

    public function attachAsChild(int $childId, int $parentId): void
    {
        if ($childId === $parentId) {
            throw new \InvalidArgumentException('Un nodo no puede ser su propio padre.');
        }

        DB::transaction(function () use ($childId, $parentId) {
            $isDescendant = PersonClosure::query()
                ->where('ancestor_id', $childId)
                ->where('descendant_id', $parentId)
                ->exists();

            if ($isDescendant) {
                throw new \RuntimeException('Movimiento inválido: crearía un ciclo.');
            }

            // Ancestros del padre (incluido él mismo)
            $parentAncestors = PersonClosure::query()
                ->where('descendant_id', $parentId)
                ->get(['ancestor_id', 'depth'])
                ->map(fn ($r) => ['id' => (int)$r->ancestor_id, 'depth' => (int)$r->depth])
                ->all();

            // Descendientes del hijo (incluido él mismo)
            $childDescendants = PersonClosure::query()
                ->where('ancestor_id', $childId)
                ->get(['descendant_id', 'depth'])
                ->map(fn ($r) => ['id' => (int)$r->descendant_id, 'depth' => (int)$r->depth])
                ->all();

            $pairs = [];
            foreach ($parentAncestors as $pa) {
                foreach ($childDescendants as $ch) {
                    $pairs[] = [$pa['id'], $ch['id']];
                }
            }

            if (empty($pairs)) {
                return;
            }

            $existing = PersonClosure::query()
                ->whereIn('ancestor_id', array_unique(array_column($pairs, 0)))
                ->whereIn('descendant_id', array_unique(array_column($pairs, 1)))
                ->get(['ancestor_id', 'descendant_id', 'depth'])
                ->reduce(function ($carry, $r) {
                    $carry[$r->ancestor_id.'|'.$r->descendant_id] = (int)$r->depth;
                    return $carry;
                }, []);

            $rows = [];
            foreach ($parentAncestors as $pa) {
                foreach ($childDescendants as $ch) {
                    $newDepth = $pa['depth'] + $ch['depth'] + 1;
                    $key = $pa['id'].'|'.$ch['id'];
                    if (!isset($existing[$key]) || $newDepth < $existing[$key]) {
                        $rows[] = [
                            'ancestor_id'   => $pa['id'],
                            'descendant_id' => $ch['id'],
                            'depth'         => $newDepth,
                        ];
                    }
                }
            }

            if (!empty($rows)) {
                PersonClosure::upsert(
                    $rows,
                    ['ancestor_id', 'descendant_id'],
                    ['depth']
                );
            }
        });
    }

    public function moveSubtree(int $nodeId, int $newParentId): void
    {
        if ($nodeId === $newParentId) {
            throw new \InvalidArgumentException('No puedes mover un nodo debajo de sí mismo.');
        }

        DB::transaction(function () use ($nodeId, $newParentId) {

            // 1) Evitar ciclos: ¿nuevo padre es descendiente del nodo?
            $isDescendant = PersonClosure::query()
                ->where('ancestor_id', $nodeId)
                ->where('descendant_id', $newParentId)
                ->exists();
            if ($isDescendant) {
                throw new \RuntimeException('Movimiento inválido: crearía un ciclo.');
            }

            // 2) Listas auxiliares
            $descendants = PersonClosure::query()
                ->where('ancestor_id', $nodeId)
                ->pluck('descendant_id')
                ->map(fn ($v) => (int)$v)
                ->all();

            if (empty($descendants)) {
                return; // nada que mover
            }

            $ancestors = PersonClosure::query()
                ->where('descendant_id', $nodeId)
                ->pluck('ancestor_id')
                ->map(fn ($v) => (int)$v)
                ->all();

            // 3) Eliminar conexiones: ancestros externos -> descendientes del subárbol
            PersonClosure::query()
                ->whereIn('ancestor_id', $ancestors)
                ->whereIn('descendant_id', $descendants)
                ->whereNotIn('ancestor_id', $descendants) // conservar caminos internos del subárbol
                ->delete();

            // 4) Reconectar: (ancestros del nuevo padre) x (descendientes del nodo)
            $parentAncestors = PersonClosure::query()
                ->where('descendant_id', $newParentId)
                ->get(['ancestor_id', 'depth'])
                ->map(fn ($r) => ['id' => (int)$r->ancestor_id, 'depth' => (int)$r->depth])
                ->all();

            $nodeDescendants = PersonClosure::query()
                ->where('ancestor_id', $nodeId)
                ->get(['descendant_id', 'depth'])
                ->map(fn ($r) => ['id' => (int)$r->descendant_id, 'depth' => (int)$r->depth])
                ->all();

            // Mapa de existentes después de la limpieza
            $pairs = [];
            foreach ($parentAncestors as $pa) {
                foreach ($nodeDescendants as $nd) {
                    $pairs[] = [$pa['id'], $nd['id']];
                }
            }

            $existing = PersonClosure::query()
                ->whereIn('ancestor_id', array_unique(array_column($pairs, 0)))
                ->whereIn('descendant_id', array_unique(array_column($pairs, 1)))
                ->get(['ancestor_id', 'descendant_id', 'depth'])
                ->reduce(function ($carry, $r) {
                    $carry[$r->ancestor_id.'|'.$r->descendant_id] = (int)$r->depth;
                    return $carry;
                }, []);

            $rows = [];
            foreach ($parentAncestors as $pa) {
                foreach ($nodeDescendants as $nd) {
                    $newDepth = $pa['depth'] + $nd['depth'] + 1;
                    $key = $pa['id'].'|'.$nd['id'];
                    if (!isset($existing[$key]) || $newDepth < $existing[$key]) {
                        $rows[] = [
                            'ancestor_id'   => $pa['id'],
                            'descendant_id' => $nd['id'],
                            'depth'         => $newDepth,
                        ];
                    }
                }
            }

            if (!empty($rows)) {
                PersonClosure::upsert(
                    $rows,
                    ['ancestor_id', 'descendant_id'],
                    ['depth']
                );
            }
        });
    }

    public function deletePerson(int $nodeId): void
    {
        DB::transaction(function () use ($nodeId) {
            Person::findOrFail($nodeId);

            $ids = PersonClosure::query()
                ->where('ancestor_id', $nodeId)
                ->pluck('descendant_id')
                ->all();

            if (empty($ids)) return;

            PersonClosure::query()
                ->whereIn('ancestor_id', $ids)
                ->orWhereIn('descendant_id', $ids)
                ->delete();

            Person::query()->whereIn('id', $ids)->delete();
        });
    }

    public function bfs(int $rootId): array
    {
        // 1) Todos los nodos del subárbol (incluye al root por depth=0)
        $nodeIds = PersonClosure::query()
            ->where('ancestor_id', $rootId)
            ->pluck('descendant_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        if (empty($nodeIds)) {
            return []; // por si el root no existe en clausura
        }

        // 2) Datos de personas
        $people = Person::query()
            ->whereIn('id', $nodeIds)
            ->get(['id', 'name'])
            ->keyBy('id'); // id => Person

        // 3) Aristas directas (padre->hijo) del subárbol
        $edges = PersonClosure::query()
            ->whereIn('ancestor_id', $nodeIds)
            ->where('depth', 1)
            ->get(['ancestor_id', 'descendant_id']);

        // 4) Adjacencia parent -> [children] (ordenada por id para determinismo)
        $adj = [];
        foreach ($edges as $e) {
            $adj[$e->ancestor_id][] = (int) $e->descendant_id;
        }
        foreach ($adj as &$arr) {
            sort($arr); // o usa sort por nombre si prefieres
        }

        // 5) BFS
        $result = [];
        $queue = [[$rootId, 0]];
        while (!empty($queue)) {
            [$id, $depth] = array_shift($queue);
            // seguridad: puede que falte el registro si root no existe
            if (!isset($people[$id])) { continue; }

            $result[] = [
                'id'    => $id,
                'name'  => $people[$id]->name,
                'depth' => $depth,
            ];

            if (isset($adj[$id])) {
                foreach ($adj[$id] as $childId) {
                    $queue[] = [$childId, $depth + 1];
                }
            }
        }

        return $result;
    }

    public function dfs(int $rootId): array
    {
        // 1) Todos los nodos del subárbol
        $nodeIds = PersonClosure::query()
            ->where('ancestor_id', $rootId)
            ->pluck('descendant_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        if (empty($nodeIds)) {
            return [];
        }

        // 2) Datos de personas
        $people = Person::query()
            ->whereIn('id', $nodeIds)
            ->get(['id', 'name'])
            ->keyBy('id');

        // 3) Aristas directas depth=1
        $edges = PersonClosure::query()
            ->whereIn('ancestor_id', $nodeIds)
            ->where('depth', 1)
            ->get(['ancestor_id', 'descendant_id']);

        // 4) Adjacencia parent -> [children] (ordenada por id)
        $adj = [];
        foreach ($edges as $e) {
            $adj[$e->ancestor_id][] = (int) $e->descendant_id;
        }
        foreach ($adj as &$arr) {
            sort($arr); // orden determinista; invierte si quieres por nombre
        }

        // 5) DFS pre-order (iterativo con pila para evitar recursion limit)
        $result = [];
        // pila de [id, depth]; empujamos hijos en orden inverso para que salgan asc
        $stack = [[$rootId, 0]];

        while (!empty($stack)) {
            [$id, $depth] = array_pop($stack);
            if (!isset($people[$id])) { continue; }

            $result[] = [
                'id'    => $id,
                'name'  => $people[$id]->name,
                'depth' => $depth,
            ];

            if (isset($adj[$id])) {
                // push en orden inverso para que el primero quede arriba de la pila
                $children = $adj[$id];
                for ($i = count($children) - 1; $i >= 0; $i--) {
                    $stack[] = [$children[$i], $depth + 1];
                }
            }
        }

        return $result;
    }

    public function findById(int $id): ?Person
    {
        return Person::find($id);
    }

    public function maxDepthFrom(int $rootId): int
    {
        return (int) PersonClosure::query()
            ->where('ancestor_id', $rootId)
            ->max('depth');
    }

    public function countDescendants(int $nodeId): int
    {
        return (int) PersonClosure::query()
            ->where('ancestor_id', $nodeId)
            ->where('depth', '>', 0)
            ->count();
    }
}
