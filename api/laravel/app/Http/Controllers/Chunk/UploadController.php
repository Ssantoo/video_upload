<?php

namespace  App\Http\Controllers\Chunk;

use App\Http\Controllers\Controller;
use App\Services\StorageService;
use Illuminate\Http\Request;


class UploadController extends Controller
{
    protected StorageService $storageService;

    public function __construct(StorageService $storageService)
    {
        $this->storageService = $storageService;
    }

    public function uploadChunck(Request $request)
    {
        $chunkNumber = $request->input('chunkNumber');
        $totalChunks = $request->input('totalChunks');

        $file = $request->file('chunk');
        $key = $request->input('key');
        $storage = $request->input('s3');

        try {
            $result = $this->storageService->uploadChunk($storage, $file, $key, $chunkNumber, $totalChunks);

            if ($result && $chunkNumber < $totalChunks -1 ){
                return response()->json(['message' => '청크업로드 성공'], 206);
            } elseif ($result){
                return response()->json(['message' => '파일업로드 성공'], 200);
            }

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }


    }

}
