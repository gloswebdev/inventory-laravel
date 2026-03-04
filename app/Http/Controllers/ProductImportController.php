<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\ProductType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductImportController extends Controller
{
    public function import(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|file',
        ]);

        $file = $request->file('excel_file');
        $path = $file->getRealPath();

        $zip = new \ZipArchive;
        if ($zip->open($path) === TRUE) {
            
            // 1. Read Shared Strings
            $sharedStrings = [];
            if ($xmlData = $zip->getFromName('xl/sharedStrings.xml')) {
                $xml = simplexml_load_string($xmlData);
                foreach ($xml->si as $si) {
                    $sharedStrings[] = (string)$si->t;
                }
            }

            // 2. Read Sheet 1
            if ($xmlData = $zip->getFromName('xl/worksheets/sheet1.xml')) {
                $xml = simplexml_load_string($xmlData);
                
                DB::beginTransaction();
                try {
                    $count = 0;
                    foreach ($xml->sheetData->row as $row) {
                        $rowIdx = (int)$row['r'];
                        if ($rowIdx == 1) continue; // Skip Header Row

                        $rowData = [];
                        // Initialize row data with empty strings for expected columns (0 to 11)
                        for ($i = 0; $i <= 11; $i++) {
                             $rowData[$i] = '';
                        }

                        // Parse cells
                        foreach ($row->c as $cell) {
                            $cellRef = (string)$cell['r'];
                            // Extract column index from cell ref (e.g., A1, B2)
                            preg_match('/([A-Z]+)(\d+)/', $cellRef, $matches);
                            $colLetter = $matches[1] ?? 'A';
                            
                            // Convert column letter to 0-based index
                            $colIndex = 0;
                            $strLen = strlen($colLetter);
                            for ($i = 0; $i < $strLen; $i++) {
                                $colIndex = $colIndex * 26 + (ord($colLetter[$i]) - 64);
                            }
                            $colIndex -= 1; // 0-indexed

                            $val = (string)$cell->v;
                            $t = (string)$cell['t'];
                            
                            if ($t == 's') {
                                $val = isset($sharedStrings[(int)$val]) ? $sharedStrings[(int)$val] : $val;
                            }
                            $rowData[$colIndex] = $val;
                        }

                        // Mapping based on "Item Directory.xlsx" headers:
                        // 0: SNO.
                        // 1: ITEM CODE
                        // 2: CATEGORY
                        // 3: FORM
                        // 4: TECHNICAL NAME
                        // 5: RM TYPE
                        // 6: TYPE
                        // 7: ITEM NAME
                        // 8: PACK NAME
                        // 9: UNIT/BOX
                        // 10: WEIGHT/UNIT
                        // 11: WEIGHT(IN)

                        $itemCode = trim($rowData[1] ?? '');
                        $categoryRaw = trim($rowData[2] ?? '');
                        $form = trim($rowData[3] ?? '');
                        $technicalName = trim($rowData[4] ?? '');
                        $rmType = trim($rowData[5] ?? '');
                        $typeName = trim($rowData[6] ?? '');
                        $itemName = trim($rowData[7] ?? '');
                        $packName = trim($rowData[8] ?? '');
                        $unitBox = trim($rowData[9] ?? '');
                        $weightUnit = trim($rowData[10] ?? '');
                        $weightIn = trim($rowData[11] ?? '');

                        if (!$itemName) continue;

                        // Find or Create Group from CATEGORY
                        $groupId = null;
                        if ($categoryRaw && $categoryRaw !== '(NIL)') {
                            $group = ProductGroup::firstOrCreate(['group_name' => $categoryRaw]);
                            $groupId = $group->id;
                        }

                        // Find or Create Type from TYPE
                        $typeId = null;
                        if ($typeName && $typeName !== '(NIL)') {
                            $type = ProductType::firstOrCreate(['type_name' => $typeName]);
                            $typeId = $type->id;
                        }
                        
                        if (!$typeId) {
                            $defaultType = ProductType::firstOrCreate(['type_name' => 'General']);
                            $typeId = $defaultType->id;
                        }

                        $searchAttributes = $itemCode ? ['item_code' => $itemCode] : ['name' => $itemName];
                        
                        Product::updateOrCreate(
                            $searchAttributes,
                            [
                                'name' => $itemName, // Ensure name is always set/updated
                                'item_code' => $itemCode,
                                'alias_name' => null, 
                                'pack_name' => $packName,
                                'group_id' => $groupId,
                                'category' => $categoryRaw,
                                'product_type_id' => $typeId,
                                'form' => $form,
                                'technical_name' => $technicalName,
                                'rm_type' => $rmType,
                                'unit_box' => $unitBox,
                                'weight_unit' => $weightUnit,
                                'weight_in' => $weightIn,
                                'uom' => $weightIn ?: 'N/A', 
                                'price' => 0, 
                                'low_alert_quantity' => 0, 
                            ]
                        );
                        $count++;
                    }
                    
                    DB::commit();
                    $zip->close();
                    return redirect()->back()->with('success', "$count products imported successfully.");

                } catch (\Exception $e) {
                    DB::rollBack();
                    $zip->close();
                    return redirect()->back()->with('error', 'Import failed: ' . $e->getMessage());
                }
            } else {
                $zip->close();
                return redirect()->back()->with('error', 'Sheet1 not found in Excel file.');
            }
        } else {
             return redirect()->back()->with('error', 'Failed to open Excel file. Make sure it is a valid .xlsx file.');
        }
    }


    public function downloadTemplate()
    {
        $headers = [
            'SNO.',
            'ITEM CODE',
            'CATEGORY',
            'FORM',
            'TECHNICAL NAME',
            'RM TYPE',
            'TYPE',
            'ITEM NAME',
            'PACK NAME',
            'UNIT/BOX',
            'WEIGHT/UNIT',
            'WEIGHT(IN)'
        ];

        $callback = function() use ($headers) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $headers);
            
            // Add a sample row
            fputcsv($file, [
                '1', 'ITEM001', 'Raw Material', 'Solid', 'Tech Name', 'Type A', 'Finished Good', 'Sample Item', 'Pack 1', '10', '1', 'KG'
            ]);
            
            fclose($file);
        };

        return response()->stream($callback, 200, [
            "Content-Type" => "text/csv",
            "Content-Disposition" => "attachment; filename=product_import_template.csv",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        ]);
    }
}
