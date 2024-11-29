<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;

class TaskController extends Controller
{
    /**
     * แสดงรายการ tasks ทั้งหมด
     */
    public function index()
    {
        try {
            $tasks = Task::all();
            
            if ($tasks->isEmpty()) {
                return response()->json([
                    'message' => 'No tasks found',
                    'data' => []
                ], Response::HTTP_OK);
            }

            return TaskResource::collection($tasks);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error retrieving tasks',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * สร้าง task ใหม่
     */
    public function store(StoreTaskRequest $request): JsonResponse
    {
        try {
            $task = Task::create($request->validated());

            return response()->json([
                'message' => 'Task created successfully',
                'data' => new TaskResource($task)
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error creating task',
                'error' => $e->getMessage()
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
            return response()->json([
                'message' => 'Task retrieved successfully',
                'data' => new TaskResource($task)
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Task not found',
                'error' => "Unable to find task with ID: {$id}"
            ], Response::HTTP_NOT_FOUND);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error retrieving task',
                'error' => $e->getMessage()
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
            
            // เช็คว่ามีการเปลี่ยนแปลงข้อมูลหรือไม่
            if ($task->update($request->validated())) {
                return response()->json([
                    'message' => 'Task updated successfully',
                    'data' => new TaskResource($task)
                ]);
            }

            return response()->json([
                'message' => 'No changes were made to the task',
                'data' => new TaskResource($task)
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Task not found',
                'error' => "Unable to find task with ID: {$id}"
            ], Response::HTTP_NOT_FOUND);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error updating task',
                'error' => $e->getMessage()
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
            
            if ($task->delete()) {
                return response()->json([
                    'message' => 'Task deleted successfully'
                ]);
            }

            return response()->json([
                'message' => 'Error deleting task',
                'error' => 'Task could not be deleted'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Task not found',
                'error' => "Unable to find task with ID: {$id}"
            ], Response::HTTP_NOT_FOUND);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error deleting task',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}