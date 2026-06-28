<?php

namespace App\Http\Controllers;

use App\Models\Section;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

class SectionController extends Controller
{
    
    public function index()
    {
        $sections = Section::all();
        return view('super_admin_view.sections.index',compact('sections'));
    }

   
    public function create()
    {
        $main_sections  = Section::mainSections()->orderBy('order')->get();
        $sub_sections  = Section::where('sub_of','!=',0)->orderBy('order')->get();

        return view('super_admin_view.sections.form',compact('main_sections','sub_sections'));
    }

    public function store(Request $request)
    {
        $section = Section::create($request->all());
        // $section->route !== null ? $this->permissionsForSections($section) : "";
        return redirect()->back();
    }

 
    public function show(Section $section)
    {
        //
    }

    
    public function edit(Section $section)
    {
        $main_sections  = Section::mainSections()->orderBy('order')->get();
        $sub_sections  = Section::where('sub_of','!=',0)->orderBy('order')->get();

        return view('super_admin_view.sections.form',compact('section','main_sections','sub_sections'));
    }

   
    public function update(Request $request, Section $section)
    {
        $section->update($request->all());

        return redirect()->back();
    }

    public function destroy(Section $section)
    {
        //
    }
    public function permissionsForSections($section)
    {
        $route = $section->route;
        $route_array = explode('.',$route);
        $route = $route_array[0];
        Permission::create(['name' => 'view '.$route]);
        Permission::create(['name' => 'create '.$route]);
        Permission::create(['name' => 'edit '.$route]);
        Permission::create(['name' => 'delete '.$route]);
    }
}
