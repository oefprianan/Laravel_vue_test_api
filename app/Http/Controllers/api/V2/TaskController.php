<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class TaskController extends Controller
{
    use AuthorizesRequests;

    /**
     * แสดงรายการ tasks ทั้งหมด
     */
    public function index(): JsonResponse
    {
        try {
            $this->authorize('viewAny', Task::class);

            $tasks = Task::where('user_id', Auth::id())->get();

            return response()->json([
                'message' => $tasks->isEmpty() ? 'No tasks found' : 'Tasks retrieved successfully',
                'data' => TaskResource::collection($tasks),
            ], Response::HTTP_OK);

        } catch (AuthorizationException $e) {
            return response()->json([
                'message' => 'Unauthorized',
                'error' => 'You are not authorized to view tasks',
            ], Response::HTTP_FORBIDDEN);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error retrieving tasks',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * สร้าง task ใหม่
     */
    public function store(StoreTaskRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', Task::class);

            $task = new Task($request->validated());
            $task->user_id = Auth::id();
            $task->save();

            return response()->json([
                'message' => 'Task created successfully',
                'data' => new TaskResource($task),
            ], Response::HTTP_CREATED);

        } catch (AuthorizationException $e) {
            return response()->json([
                'message' => 'Unauthorized',
                'error' => 'You are not authorized to create tasks',
            ], Response::HTTP_FORBIDDEN);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error creating task',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * แสดงรายละเอียดของ task ที่ระบุ
     */
    public function show(int $id): JsonResponse
    {
        try {
            $task = Task::findOrFail($id);
            $this->authorize('view', $task);

            return response()->json([
                'message' => 'Task retrieved successfully',
                'data' => new TaskResource($task),
            ], Response::HTTP_OK);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Task not found',
                'error' => "Unable to find task with ID: {$id}",
            ], Response::HTTP_NOT_FOUND);
        } catch (AuthorizationException $e) {
            return response()->json([
                'message' => 'Unauthorized',
                'error' => 'You are not authorized to view this task',
            ], Response::HTTP_FORBIDDEN);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error retrieving task',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * อัพเดท task ที่ระบุ
     */
    public function update(UpdateTaskRequest $request, int $id): JsonResponse
    {
        try {
            $task = Task::findOrFail($id);
            $this->authorize('update', $task);

            $updated = $task->update($request->validated());

            return response()->json([
                'message' => $updated ? 'Task updated successfully' : 'No changes were made to the task',
                'data' => new TaskResource($task),
            ], Response::HTTP_OK);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Task not found',
                'error' => "Unable to find task with ID: {$id}",
            ], Response::HTTP_NOT_FOUND);
        } catch (AuthorizationException $e) {
            return response()->json([
                'message' => 'Unauthorized',
                'error' => 'You are not authorized to update this task',
            ], Response::HTTP_FORBIDDEN);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error updating task',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * ลบ task ที่ระบุ
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $task = Task::findOrFail($id);
            $this->authorize('delete', $task);

            if ($task->delete()) {
                return response()->json([
                    'message' => 'Task deleted successfully',
                ], Response::HTTP_OK);
            }

            throw new \Exception('Task could not be deleted');

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Task not found',
                'error' => "Unable to find task with ID: {$id}",
            ], Response::HTTP_NOT_FOUND);
        } catch (AuthorizationException $e) {
            return response()->json([
                'message' => 'Unauthorized',
                'error' => 'You are not authorized to delete this task',
            ], Response::HTTP_FORBIDDEN);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error deleting task',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
