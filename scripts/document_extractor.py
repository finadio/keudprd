import sys
import os
import json

def extract_from_image(image_path):
    from rapidocr_onnxruntime import RapidOCR
    ocr = RapidOCR()
    result, elapse_list = ocr(image_path)
    lines = []
    if result:
        # Sort lines top-to-bottom, left-to-right
        sorted_res = sorted(result, key=lambda item: (round(item[0][0][1] / 15) * 15, item[0][0][0]))
        for item in sorted_res:
            text = item[1].strip()
            if text:
                lines.append(text)
    return lines

def extract_from_pdf(pdf_path):
    lines = []
    # First attempt pypdf text extraction
    has_text = False
    try:
        import pypdf
        reader = pypdf.PdfReader(pdf_path)
        for page_num, page in enumerate(reader.pages):
            page_text = page.extract_text() or ''
            if len(page_text.strip()) > 30:
                has_text = True
                for l in page_text.splitlines():
                    if l.strip():
                        lines.append(l.strip())
    except Exception:
        pass

    # If digital text was found and substantial, return it
    if has_text and len(lines) > 5:
        return lines

    # Otherwise, fallback to OCR on scanned PDF pages
    try:
        import pypdfium2 as pdfium
        from rapidocr_onnxruntime import RapidOCR
        ocr = RapidOCR()
        pdf = pdfium.PdfDocument(pdf_path)
        for i in range(len(pdf)):
            page = pdf[i]
            # Render page to PIL image (dpi ~200 for good OCR)
            image = page.render(scale=2).to_pil()
            temp_img = f"{pdf_path}_page_{i}.png"
            image.save(temp_img)
            try:
                result, _ = ocr(temp_img)
                if result:
                    sorted_res = sorted(result, key=lambda item: (round(item[0][0][1] / 15) * 15, item[0][0][0]))
                    for item in sorted_res:
                        text = item[1].strip()
                        if text:
                            lines.append(text)
            finally:
                if os.path.exists(temp_img):
                    os.remove(temp_img)
    except Exception as e:
        if not lines:
            raise e

    return lines

def extract_from_docx(docx_path):
    import docx
    doc = docx.Document(docx_path)
    lines = []
    for p in doc.paragraphs:
        if p.text.strip():
            lines.append(p.text.strip())
    for t in doc.tables:
        for r in t.rows:
            row_cells = [c.text.strip() for c in r.cells if c.text.strip()]
            if row_cells:
                lines.append(" \t ".join(row_cells))
    return lines

def main():
    if len(sys.argv) < 2:
        print(json.dumps({"success": False, "error": "No file path provided"}))
        sys.exit(1)

    file_path = sys.argv[1]
    if not os.path.exists(file_path):
        print(json.dumps({"success": False, "error": f"File not found: {file_path}"}))
        sys.exit(1)

    ext = os.path.splitext(file_path)[1].lower()

    try:
        if ext in ['.png', '.jpg', '.jpeg', '.webp', '.bmp', '.tiff']:
            lines = extract_from_image(file_path)
        elif ext == '.pdf':
            lines = extract_from_pdf(file_path)
        elif ext in ['.docx', '.doc']:
            lines = extract_from_docx(file_path)
        elif ext in ['.txt', '.csv']:
            with open(file_path, 'r', encoding='utf-8', errors='ignore') as f:
                lines = [l.strip() for l in f if l.strip()]
        else:
            print(json.dumps({"success": False, "error": f"Unsupported extension: {ext}"}))
            sys.exit(1)

        print(json.dumps({
            "success": True,
            "total_lines": len(lines),
            "lines": lines,
            "text": "\n".join(lines)
        }))
    except Exception as e:
        print(json.dumps({"success": False, "error": str(e)}))
        sys.exit(1)

if __name__ == '__main__':
    main()
