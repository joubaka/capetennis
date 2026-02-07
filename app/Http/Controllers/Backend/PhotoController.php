<?php

namespace App\Http\Controllers\backend;

use App\Http\Controllers\Controller;
use App\Models\Event;

use App\Models\Photo;
use App\Models\PhotoFolder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class PhotoController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {

        $data['photos'] = Event::where('sell_food', 3)->get();

        return view('backend.photo.index', $data);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('backend.photo.upload');
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
            'image.*' => 'mimes:doc,pdf,docx,zip,jpeg,png,jpg,gif,svg',

        ]);



        //check if file exist 
        if ($request->hasFile('images')) {

            foreach ($request->file('images') as $imagefile) {
                $file = $imagefile;

                $image = new Photo();
                $image->name = $file->getClientOriginalName();
                $image->type = $file->getClientOriginalExtension();
                $image->size = $file->getSize();
                $image->path = $request->folder_id . '/' . $file->getClientOriginalName();
                $image->event_id = $request->event_id;
                $image->folder_id = $request->folder_id;
                $image->save();

                $filename = $file->getClientOriginalName();

                $file->storeAs('public/photoFolder/' . $request->folder_id . '/', $filename);
            }




            return redirect()->back();
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

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
    public function destroy($id)
    {


        $photo =  Photo::find($id);
        $path = public_path() . '/storage/photoFolder/' . $photo->path;


        if (File::exists($path)) {
            //File::delete($image_path);
            unlink($path);
        }
        $photo->delete();
        return redirect()->back();
    }

    public function deleteSelected(Request $request)
    {
        $values = $request->data;
        $photos = Photo::whereIn('id', $values)->delete();
        return count($values) . 'Photos Deleted';
    }

    public function moveSelected(Request $request)
    {
       
        foreach($request->data as $photo){
            $p = Photo::find($photo);
            $f = PhotoFolder::find($request->folder_id);


             File::move(public_path() . '/storage/photoFolder/' . $p->path , public_path() . '/storage/photoFolder/' . $f->id.'/'.$p->name);
            $temp = Photo::where('id',$photo)->update(['folder_id' => $request->folder_id,'path' => $f->id.'/'.$p->name ]);
           
        }

        $values = $request->data;
       // $photos = Photo::whereIn('id', $values)->delete();
        return count($values) . ' Photos Moved';
    }
}
