# Project Cloud City training planner

Download the complete `trainingplan.html` file from the **planner-update** branch using GitHub's **Download raw file** button. It includes the training plan and meal library in one HTML file. Open it in a modern browser, or replace the existing HTML on your own web server.

Before replacing an older copy, use **Export Backup (.json)**. Browser data belongs to the browser and site/file location; GitHub does not store your workout or food logs. Use **Import Backup** to carry your data to another browser or location. The full backup includes library edits, combinations, favorites, eaten meals, day settings, and workouts. Library JSON export/import transfers library entries separately.

## Meals & Fueling

- Rest days: **1,650 kcal**.
- Exercise days: **1,650 + reported exercise calories − 500 kcal**.
- Double sessions use individual session calories once. Older logs without individual calorie values use the combined daily total once.
- The date selector links meals to that date's workout. Day settings let you select rest/exercise explicitly or correct the reported calorie total. A correction replaces the total rather than adding to it.
- No logged exercise defaults to a rest day. A logged exercise session with missing calorie burn is flagged; enter the reported calories to get meal suggestions. Future plan prescriptions alone do not count as completed exercise.
- Recommendations fit the remaining calorie budget and favor foods you actually log and mark as favorites. Swapping a suggestion does not count as eating it or disliking it. Suggestions are never automatically logged. Adjust portions and save only food you ate.
- Add/edit foods and meals, archive/restore entries, and save combinations with **Mix & match**. Library edits do not rewrite already-eaten meals; food logs keep snapshots of the original nutrition.
- The workbook supplies 19 grouped meals, 30 exact food portions from 19 food-note entries, 210 food-history names, and one custom food with missing nutrition. Name-only entries must be given a serving and nutrition before logging or recommending. Original meal totals are preserved even where ingredient sums differ due to recorded rounding or incomplete data.
- There are no macro adherence or weight goal cards. Protein, carbohydrate, and fat amounts are displayed as food information, without hard-coded targets.

## Storage and optional NAS

The app works with browser-local storage. Export backups regularly, especially before changing file locations, clearing browser data, or syncing devices. Images and logs consume browser storage. The nutrition module needs no external library; screenshot OCR still uses the existing Tesseract CDN.

NAS synchronization requires your own compatible `api.php` and NAS token. No PHP backend is included in this repository. The client sends the existing revision/store API, now including `store.nutrition`. Food and day-setting records merge by their latest edit; individual meal entries merge independently, with removal tombstones to prevent deleted meals reappearing. Existing workout-day conflicts retain the prior explicit merge behavior: this device's workout wins.

After a NAS write, the client reads back nutrition data to confirm that the backend retained it. If the backend drops fields, automatic syncing pauses and local data is kept. This behavior was tested against a simulated API; a real Synology NAS connection was not available for validation. Serve the HTML alongside a compatible backend to use NAS sync; opening a downloaded HTML or viewing it on GitHub does not provide that backend.
