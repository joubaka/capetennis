<?php

namespace App\Http\Controllers\backend;


use App\Http\Controllers\Controller;
use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;

use App\Models\event_pdf_draw;

use App\Models\pdfDraw;
use Illuminate\Contracts\View\View;



class FileController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(): View
    {
       dd('index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */



  public function store(Request $request)
  {
    $request->validate([
      'myFile' => 'required|mimes:pdf,doc,docx,xls,xlsx,csv|max:5120', // ✅ allows Word, Excel, PDF up to 5MB
      'event_id' => 'required|integer',
    ]);

    $uploadedFile = $request->file('myFile');
    $originalName = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
    $extension = $uploadedFile->getClientOriginalExtension();

    // Store file inside storage/app/public/files
    $path = $uploadedFile->storeAs('public/files', $originalName . '.' . $extension);

    // Save record in DB
    $file = new File();
    $file->name = $originalName . '.' . $extension;
    $file->event_id = $request->event_id;
    $file->path = $path;
    $file->save();

    return back()->with([
      'success' => 'File uploaded successfully!',
      'file' => $file
    ]);
  }


  public function show($id)
  {
    $file = File::findOrFail($id);

    // Normalize path — only prepend storage_path() if it's not already absolute
    $path = $file->path;
    if (!str_starts_with($path, '/')) {
      $path = storage_path('app/' . ltrim($path, '/'));
    }

    // Check if file exists before continuing
    if (!file_exists($path)) {
      abort(404, 'File not found: ' . $path);
    }

    // Safely detect MIME type
    $mime = mime_content_type($path);

    return response()->file($path, [
      'Content-Type' => $mime,
      'Content-Disposition' => 'inline; filename="' . basename($path) . '"'
    ]);
  }

  public function destroy($id)
  {
    $file = File::find($id);

    if (!$file) {
      return response()->json(['success' => false, 'msg' => 'File not found.'], 404);
    }

    // Delete file from storage
    $path = $file->path;
    if (!str_starts_with($path, '/')) {
      $path = storage_path('app/' . ltrim($path, '/'));
    }

    if (file_exists($path)) {
      @unlink($path);
    }

    // Delete database record
    $file->delete();

    return response()->json(['success' => true, 'msg' => 'File deleted successfully.']);
  }


  /**
   * Display the specified resource.
   *
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
   

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
   
}
