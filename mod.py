import os
import re

# Mapping of HTML file names to corresponding GLB file names
model_map = {
    "us1.html": "Explorer_1.glb",
    "us2.html": "Apollo_11.glb",  # Not listed; assumed, or skip if not found
    "us3.html": "Voyager.glb",
    "us4.html": "Shuttle.glb",
    "us5.html": "ISS.glb",
    "us6.html": "Perseverance.glb",
    "us7.html": "New_Horizons.glb",
    "us8.html": "jwt.glb",
    "us9.html": "Telstar.glb",  # Not shown; placeholder or skip
    "us10.html": "spacex_falcon_9_v4.glb",
    "us11.html": "hubble_space_telescope.glb",
    "us12.html": "Dragon.glb",  # Not in list; assumed
    "us13.html": "Gemini Spacesuit.glb",
    "us14.html": "Saturn_V.glb",
    "us15.html": "artemis_sls_rocket.glb",
    "us16.html": "V-2_camera.glb",  # Not in list; assumed
    "us17.html": "Apollo_12.glb",   # Not in list; assumed
    "us18.html": "mercury-_redstone_launch_vehicle.glb",
    "us19.html": "WAC_Corporal.glb",  # Not in list
    "us20.html": "Saturn_V.glb",
    "us21.html": "xyz_school_course_work.glb"
}

# Loop over HTML files and update model-viewer path
for html_file, glb_file in model_map.items():
    if not os.path.exists(html_file):
        print(f"Skipping missing file: {html_file}")
        continue

    with open(html_file, "r", encoding="utf-8") as f:
        content = f.read()

    # Replace src inside <model-viewer>
    updated_content = re.sub(
        r'<model-viewer\s+[^>]*src="[^"]+"',
        f'<model-viewer src="3d/{glb_file}"',
        content
    )

    with open(html_file, "w", encoding="utf-8") as f:
        f.write(updated_content)

    print(f"Updated: {html_file} → 3d/{glb_file}")
