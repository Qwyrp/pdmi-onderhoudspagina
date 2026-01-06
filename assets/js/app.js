const fileInput = document.getElementById("fileInput");
const fileCountSpan = document.getElementById("fileCount");
const fileCheckSpan = document.getElementById("fileCheck");
const fbCheck = document.getElementById("fbCheck");
const igCheck = document.getElementById("igCheck");
const ttCheck = document.getElementById("ttCheck");
const qualityRange = document.getElementById("qualityRange");
const qualityValue = document.getElementById("qualityValue");
const convertBtn = document.getElementById("convertBtn");
const downloadAllBtn = document.getElementById("downloadAllBtn");
const statusEl = document.getElementById("status");
const resultsEl = document.getElementById("results");
const yearSpan = document.getElementById("copyrightYear");

const generatedFiles = [];

if (yearSpan) {
  yearSpan.textContent = new Date().getFullYear();
}

if (fileInput && fileCountSpan) {
  fileInput.addEventListener("change", () => {
    const count = fileInput.files ? fileInput.files.length : 0;
    if (count === 0) {
      fileCountSpan.textContent = "";
      if (fileCheckSpan) {
        fileCheckSpan.parentElement.classList.remove("file-input--has-files");
      }
    } else if (count === 1) {
      fileCountSpan.textContent = "1 foto gekozen";
      if (fileCheckSpan) {
        fileCheckSpan.parentElement.classList.add("file-input--has-files");
      }
    } else {
      fileCountSpan.textContent = `${count} foto's gekozen`;
      if (fileCheckSpan) {
        fileCheckSpan.parentElement.classList.add("file-input--has-files");
      }
    }
  });
}

qualityRange.addEventListener("input", () => {
  qualityValue.textContent = `${qualityRange.value}%`;
});

function setStatus(message, isError = false) {
  statusEl.textContent = message;
  statusEl.style.color = isError ? "#f97373" : "#e5e7eb";
}

function readFileAsImage(file) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => {
      const img = new Image();
      img.onload = () => resolve(img);
      img.onerror = (e) => reject(e);
      img.src = reader.result;
    };
    reader.onerror = (e) => reject(e);
    reader.readAsDataURL(file);
  });
}

function resizeImage(img, targetWidth, targetHeight, mimeType = "image/jpeg", quality = 0.85) {
  const canvas = document.createElement("canvas");
  canvas.width = targetWidth;
  canvas.height = targetHeight;
  const ctx = canvas.getContext("2d");

  // Bepaal schaal en positie om de afbeelding bij te snijden (cover)
  const srcRatio = img.width / img.height;
  const targetRatio = targetWidth / targetHeight;

  let sx, sy, sw, sh;
  if (srcRatio > targetRatio) {
    // bron is breder: snij de zijkanten af
    sh = img.height;
    sw = sh * targetRatio;
    sx = (img.width - sw) / 2;
    sy = 0;
  } else {
    // bron is hoger: snij boven/onder af
    sw = img.width;
    sh = sw / targetRatio;
    sx = 0;
    sy = (img.height - sh) / 2;
  }

  ctx.drawImage(img, sx, sy, sw, sh, 0, 0, targetWidth, targetHeight);

  return new Promise((resolve) => {
    canvas.toBlob(
      (blob) => {
        resolve(blob);
      },
      mimeType,
      quality
    );
  });
}

function formatBytes(bytes) {
  if (bytes === 0) return "0 B";
  const k = 1024;
  const sizes = ["B", "KB", "MB", "GB"];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  const value = bytes / Math.pow(k, i);
  return `${value.toFixed(1)} ${sizes[i]}`;
}

function sanitizeBaseName(name) {
  return name
    .replace(/\.[^.]+$/, "")
    .replace(/[^\w\-]+/g, "_")
    .replace(/_+/g, "_")
    .replace(/^_+|_+$/g, "")
    .toLowerCase();
}

async function handleConvert() {
  const files = fileInput.files;
  if (!files || files.length === 0) {
    setStatus("Selecteer eerst één of meer foto's.", true);
    return;
  }

  const platforms = [];
  if (fbCheck.checked) platforms.push({ id: "facebook", label: "Facebook", width: 1200, height: 630 });
  // Instagram 4:5 staand (bijvoorbeeld 1080×1350), met cropping via resizeImage
  if (igCheck.checked) platforms.push({ id: "instagram", label: "Instagram", width: 1080, height: 1350 });
  if (ttCheck.checked) platforms.push({ id: "tiktok", label: "TikTok", width: 1080, height: 1920 });

  if (platforms.length === 0) {
    setStatus("Kies minimaal één platform.", true);
    return;
  }

  // Oude resultaten en object-urls opruimen
  generatedFiles.forEach((f) => URL.revokeObjectURL(f.url));
  generatedFiles.length = 0;
  downloadAllBtn.disabled = true;

  convertBtn.disabled = true;
  setStatus("Bezig met verwerken, even geduld…");
  resultsEl.innerHTML = "";

  const quality = Number(qualityRange.value) / 100;

  try {
    for (const file of files) {
      const img = await readFileAsImage(file);
      const baseName = sanitizeBaseName(file.name) || "afbeelding";

      for (const platform of platforms) {
        const blob = await resizeImage(img, platform.width, platform.height, "image/jpeg", quality);
        if (!blob) continue;

        const url = URL.createObjectURL(blob);
        const sizeStr = formatBytes(blob.size);
        const fileName = `${baseName}_${platform.id}_${platform.width}x${platform.height}.jpg`;

        const item = document.createElement("div");
        item.className = "result-item";
        item.innerHTML = `
          <div class="result-item__title">${platform.label}</div>
          <p class="result-item__meta">${fileName}</p>
          <p class="result-item__meta">${platform.width} × ${platform.height}px · ${sizeStr}</p>
        `;

        const link = document.createElement("a");
        link.href = url;
        link.download = fileName;
        link.textContent = "Download";

        item.appendChild(link);
        resultsEl.appendChild(item);

        generatedFiles.push({ url, fileName });
      }
    }

    if (generatedFiles.length > 0) {
      setStatus("Klaar! Je kunt de geconverteerde bestanden hieronder downloaden.");
      downloadAllBtn.disabled = false;
    } else {
      setStatus("Er zijn geen bestanden gegenereerd.", true);
    }
  } catch (err) {
    console.error(err);
    setStatus("Er ging iets mis tijdens het verwerken. Probeer het opnieuw.", true);
  } finally {
    convertBtn.disabled = false;
  }
}

function handleDownloadAll() {
  if (!generatedFiles.length) {
    setStatus("Er zijn nog geen bestanden om te downloaden.", true);
    return;
  }

  // Start voor elk bestand een download in dezelfde gebruikersactie
  generatedFiles.forEach(({ url, fileName }) => {
    const a = document.createElement("a");
    a.href = url;
    a.download = fileName;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
  });
}

convertBtn.addEventListener("click", handleConvert);
downloadAllBtn.addEventListener("click", handleDownloadAll);



