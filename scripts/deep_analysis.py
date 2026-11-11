import openpyxl
import json
import os
import re

def analyze_sheet(ws):
    merged = [str(m) for m in ws.merged_cells.ranges]
    max_r = ws.max_row
    max_c = ws.max_column
    
    # Read first 15 rows to get title and headers
    header_rows = []
    for r in range(1, min(16, max_r + 1)):
        row_vals = []
        for c in range(1, min(35, max_c + 1)):
            cell = ws.cell(row=r, column=c)
            val = cell.value
            row_vals.append(str(val) if val is not None else "")
        if any(row_vals):
            header_rows.append((r, row_vals))
            
    # Sample data rows (e.g. rows 10 to 20 or middle rows)
    sample_rows = []
    for r in range(max(1, min(10, max_r)), min(25, max_r + 1)):
        row_vals = []
        for c in range(1, min(35, max_c + 1)):
            cell = ws.cell(row=r, column=c)
            val = cell.value
            row_vals.append(str(val) if val is not None else "")
        if any(row_vals):
            sample_rows.append((r, row_vals))
            
    # Tail rows (last 10 rows for totals / footer)
    tail_rows = []
    for r in range(max(1, max_r - 12), max_r + 1):
        row_vals = []
        for c in range(1, min(35, max_c + 1)):
            cell = ws.cell(row=r, column=c)
            val = cell.value
            row_vals.append(str(val) if val is not None else "")
        if any(row_vals):
            tail_rows.append((r, row_vals))
            
    # Collect formulas & cross-sheet references
    formulas = []
    cross_sheet_refs = set()
    for row in ws.iter_rows(values_only=False):
        for cell in row:
            if cell.value and isinstance(cell.value, str) and cell.value.startswith("="):
                f_val = cell.value
                formulas.append((cell.coordinate, f_val))
                # match sheet names like 'SheetName'! or SheetName!
                matches = re.findall(r"('?[A-Za-z0-9_\- ]+'?)!", f_val)
                for m in matches:
                    clean_m = m.strip("'")
                    if clean_m != ws.title:
                        cross_sheet_refs.add(clean_m)
                        
    return {
        "title": ws.title,
        "max_row": max_r,
        "max_col": max_c,
        "merged_count": len(merged),
        "merged_ranges": merged[:20],
        "header_rows": header_rows[:8],
        "tail_rows": tail_rows,
        "formula_count": len(formulas),
        "sample_formulas": formulas[:20],
        "cross_sheet_refs": list(cross_sheet_refs),
    }

def main():
    print("Analyzing BKU Per PPTK 2026 (1).xlsx...")
    wb_bku = openpyxl.load_workbook("BKU Per PPTK 2026 (1).xlsx", data_only=False)
    bku_analysis = {}
    for sname in wb_bku.sheetnames:
        print(f"  Scanning sheet: {sname}")
        bku_analysis[sname] = analyze_sheet(wb_bku[sname])
        
    with open("docs/bku_raw_analysis.json", "w", encoding="utf-8") as f:
        json.dump(bku_analysis, f, indent=2, ensure_ascii=False)
        
    print("Analyzing BUKU BANK 2026 (1).xlsx...")
    wb_bank = openpyxl.load_workbook("BUKU BANK 2026 (1).xlsx", data_only=False)
    bank_analysis = {}
    for sname in wb_bank.sheetnames:
        print(f"  Scanning sheet: {sname}")
        bank_analysis[sname] = analyze_sheet(wb_bank[sname])
        
    with open("docs/bank_raw_analysis.json", "w", encoding="utf-8") as f:
        json.dump(bank_analysis, f, indent=2, ensure_ascii=False)
        
    print("Analysis finished! JSON files generated in docs/")

if __name__ == "__main__":
    main()
