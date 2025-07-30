<?php

namespace App\Http\Controllers;

use App\Models\SupplierSelectionReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class SupplierSelectionReportController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $reports = SupplierSelectionReport::all()->map(function ($report) {
            return collect($report)->only(['id', 'code', 'description', 'file_path']);
        });

        $can = [
            'create_report' => 'Quản trị' ==  Auth::user()->role,
            'update_report' => 'Quản trị' ==  Auth::user()->role,
            'delete_report' => 'Quản trị' ==  Auth::user()->role,
        ];

        return Inertia::render('SupplierSelectionReportIndex', [
            'reports' => $reports,
            'can' => $can,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(SupplierSelectionReport $supplierSelectionReport)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(SupplierSelectionReport $supplierSelectionReport)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, SupplierSelectionReport $supplierSelectionReport)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SupplierSelectionReport $supplierSelectionReport)
    {
        //
    }
}
