<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GLB Viewer & Exporter (PNG/SVG)</title>
    <style>
        body {
            margin: 0;
            font-family: sans-serif;
            background-color: #f0f0f0;
            display: flex;
            flex-direction: column;
            height: 100vh; /* Ensure body takes full height */
            overflow: hidden; /* Prevent body scrollbars */
        }
        #viewer-container {
            flex-grow: 1; /* Takes up remaining vertical space */
            display: block;
            background-color: #cccccc;
            position: relative; /* Needed for indicator positioning */
            min-height: 200px; /* Ensure viewer has some height */
        }
        #controls {
            padding: 12px;
            background-color: #e0e0e0;
            text-align: center;
            box-shadow: 0 -2px 5px rgba(0,0,0,0.1);
            display: flex;
            flex-wrap: wrap; /* Allow wrapping on smaller screens */
            justify-content: center;
            align-items: center;
            gap: 10px; /* Spacing between elements */
            overflow-y: auto; /* Allow scrolling if controls overflow */
            flex-shrink: 0; /* Prevent controls from shrinking */
        }
        .control-group {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        button, input[type="file"], input[type="number"] {
            padding: 8px 12px;
            font-size: 0.9em;
            cursor: pointer;
            border: 1px solid #bbb;
            border-radius: 4px;
            background-color: #fff;
        }
        button:disabled {
            cursor: not-allowed;
            opacity: 0.6;
            background-color: #eee;
        }
        input[type="number"] {
             width: 65px;
             cursor: text;
         }
         label {
             margin-right: 3px;
             font-size: 0.9em;
         }
         #file-input::file-selector-button { /* Style file input button */
            padding: 8px 12px;
            border: none;
            background-color: #ddd;
            border-radius: 4px;
            margin-right: 10px;
            cursor: pointer;
         }
         #file-input::file-selector-button:hover {
             background-color: #ccc;
         }

        #loading-indicator, #exporting-indicator {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 1.5em;
            color: white;
            background-color: rgba(0,0,0,0.75);
            padding: 20px;
            border-radius: 8px;
            display: none; /* Hidden by default */
            z-index: 100; /* Ensure it's on top */
            text-align: center;
            pointer-events: none; /* Prevent interaction */
        }
         #viewer-container canvas { /* Ensure canvas is interactable */
             display: block;
             width: 100%;
             height: 100%;
         }
         .info-text {
             width: 100%;
             text-align: center;
             margin-top: 5px;
             color: #555;
             font-size: 0.8em;
             order: 100; /* Push warning to the very end */
         }
         hr {
            width:100%;
            border: none;
            border-top: 1px solid #ccc;
            margin: 5px 0;
            order: 50; /* Position separator */
         }
    </style>
<script async data-id="101482682" src="//static.getclicky.com/js"></script>
</head>
<body>

    <div id="viewer-container">
        <div id="loading-indicator">Loading Model...</div>
        <div id="exporting-indicator">Exporting...<br><small>(May take a moment or freeze)</small></div>
    </div>

    <div id="controls">
        <!-- Group 1: File Loading -->
        <div class="control-group" style="order: 1;">
            <label for="file-input">Load GLB:</label>
            <input type="file" id="file-input" accept=".glb">
        </div>

        <!-- Group 2: View Controls -->
        <div class="control-group" style="order: 2;">
            <button id="reset-view-button" disabled title="Reset camera view to initial position">Reset View</button>
            <button id="fit-view-button" disabled title="Fit model to view">Fit to View</button>
        </div>

        <hr> <!-- Separator -->

        <!-- Group 3: Export Size -->
        <div class="control-group" style="order: 51;">
            <label for="export-width">Export Width:</label>
            <input type="number" id="export-width" value="2048" min="100" max="16384" step="128">
            <span>px</span>
        </div>
         <div class="control-group" style="order: 52;">
            <label for="export-height">Height:</label>
            <input type="number" id="export-height" value="2048" min="100" max="16384" step="128">
             <span>px</span>
        </div>

        <!-- Group 4: Export Actions -->
        <div class="control-group" style="order: 53;">
            <button id="export-png-button" disabled title="Export current view as a high-res transparent PNG">Export PNG</button>
            <button id="export-svg-button" disabled title="Export current view as vector SVG (line art / basic colors)">Export SVG</button>
        </div>

        <small class="info-text">SVG export creates line art (not shaded). High resolutions may cause issues.</small>
    </div>

    <!-- Three.js Core Library & Addons -->
    <script type="importmap">
        {
            "imports": {
                "three": "https://unpkg.com/three@0.160.0/build/three.module.js",
                "three/addons/": "https://unpkg.com/three@0.160.0/examples/jsm/"
            }
        }
    </script>

    <!-- Main Application Logic -->
    <script type="module">
        import * as THREE from 'three';
        import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';
        import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
        import { SVGRenderer } from 'three/addons/renderers/SVGRenderer.js'; // <-- Import SVGRenderer

        let scene, camera, webglRenderer, controls, loadedModel, svgRenderer; // Add svgRenderer var
        let initialCameraPosition = new THREE.Vector3(0, 2, 5);
        let initialControlsTarget = new THREE.Vector3(0, 0.5, 0);

        const container = document.getElementById('viewer-container');
        const fileInput = document.getElementById('file-input');
        const exportPngButton = document.getElementById('export-png-button'); // Renamed
        const exportSvgButton = document.getElementById('export-svg-button'); // New button
        const exportWidthInput = document.getElementById('export-width');
        const exportHeightInput = document.getElementById('export-height');
        const resetViewButton = document.getElementById('reset-view-button');
        const fitViewButton = document.getElementById('fit-view-button');
        const loadingIndicator = document.getElementById('loading-indicator');
        const exportingIndicator = document.getElementById('exporting-indicator');
        const initialBackgroundColor = 0xdddddd;

        function init() {
            // --- Scene ---
            scene = new THREE.Scene();
            scene.background = new THREE.Color(initialBackgroundColor);

            // --- Camera ---
            const aspect = container.clientWidth / container.clientHeight;
            camera = new THREE.PerspectiveCamera(60, aspect, 0.1, 1000);
            camera.position.copy(initialCameraPosition);

            // --- WebGL Renderer (for viewing) ---
            webglRenderer = new THREE.WebGLRenderer({
                antialias: true,
                preserveDrawingBuffer: true, // For PNG export
                alpha: true // For transparent PNG export
            });
            webglRenderer.setClearColor(initialBackgroundColor, 1);
            webglRenderer.setSize(container.clientWidth, container.clientHeight);
            webglRenderer.setPixelRatio(window.devicePixelRatio);
            webglRenderer.outputEncoding = THREE.sRGBEncoding;
            container.appendChild(webglRenderer.domElement);

            // --- Lighting ---
            const ambientLight = new THREE.AmbientLight(0xffffff, 0.7);
            scene.add(ambientLight);
            const dirLight1 = new THREE.DirectionalLight(0xffffff, 0.6);
            dirLight1.position.set(5, 10, 7.5);
            scene.add(dirLight1);
            const dirLight2 = new THREE.DirectionalLight(0xffffff, 0.3);
            dirLight2.position.set(-5, -5, -5);
            scene.add(dirLight2);

            // --- Controls ---
            controls = new OrbitControls(camera, webglRenderer.domElement);
            controls.enableDamping = true;
            controls.dampingFactor = 0.05;
            controls.target.copy(initialControlsTarget);
            controls.update();

            // --- Event Listeners ---
            window.addEventListener('resize', onWindowResize);
            fileInput.addEventListener('change', handleFileLoad);
            exportPngButton.addEventListener('click', handleExportPNG); // Updated handler name
            exportSvgButton.addEventListener('click', handleExportSVG); // New handler
            resetViewButton.addEventListener('click', handleResetView);
            fitViewButton.addEventListener('click', handleFitView);

            animate();
        }

        function handleFileLoad(event) {
            const file = event.target.files[0];
            if (!file) {
                if (loadedModel) {
                    scene.remove(loadedModel);
                    disposeHierarchy(loadedModel);
                    loadedModel = null;
                    setLoadingState(false); // Update button states
                    handleResetView();
                }
                return;
            }

            const reader = new FileReader();

            reader.onloadstart = () => setLoadingState(true);

            reader.onload = (loadEvent) => {
                const contents = loadEvent.target.result;
                const loader = new GLTFLoader();

                loader.parse(contents, '', (gltf) => {
                    if (loadedModel) {
                        scene.remove(loadedModel);
                        disposeHierarchy(loadedModel);
                    }
                    loadedModel = gltf.scene;

                    // Center and scale
                    const box = new THREE.Box3().setFromObject(loadedModel);
                    const size = box.getSize(new THREE.Vector3());
                    const center = box.getCenter(new THREE.Vector3());
                    loadedModel.position.x += (loadedModel.position.x - center.x);
                    loadedModel.position.y += (loadedModel.position.y - center.y);
                    loadedModel.position.z += (loadedModel.position.z - center.z);
                    const maxDim = Math.max(size.x, size.y, size.z);
                    const scale = 3.0 / maxDim;
                    loadedModel.scale.setScalar(scale);

                    scene.add(loadedModel);
                    console.log("GLB Model loaded successfully!");

                    handleFitView(); // Fit after loading
                    setLoadingState(false); // Enable buttons

                }, (error) => {
                    console.error('Error loading GLB:', error);
                    alert('Failed to load GLB file. Check console.');
                    setLoadingState(false); // Reset state even on error
                });
            };

            reader.onerror = (error) => {
                console.error('Error reading file:', error);
                alert('Failed to read the file.');
                setLoadingState(false);
            };

            reader.readAsArrayBuffer(file);
        }

        function setLoadingState(isLoading) {
            loadingIndicator.style.display = isLoading ? 'block' : 'none';
            const isModelLoaded = !!loadedModel;
            fileInput.disabled = isLoading;
            exportPngButton.disabled = isLoading || !isModelLoaded;
            exportSvgButton.disabled = isLoading || !isModelLoaded;
            resetViewButton.disabled = isLoading || !isModelLoaded;
            fitViewButton.disabled = isLoading || !isModelLoaded;
            exportWidthInput.disabled = isLoading;
            exportHeightInput.disabled = isLoading;
        }

        function disposeHierarchy(obj) {
            // ... (keep this function exactly as before)
            if (!obj) return;
            obj.traverse((child) => {
                if (child.isMesh) {
                    child.geometry?.dispose();
                    if (child.material) {
                        if (Array.isArray(child.material)) {
                            child.material.forEach(material => material?.dispose());
                        } else {
                            child.material?.dispose();
                        }
                    }
                }
            });
        }

        function onWindowResize() {
            if (!webglRenderer || !camera) return;
            const width = container.clientWidth;
            const height = container.clientHeight;
            camera.aspect = width / height;
            camera.updateProjectionMatrix();
            webglRenderer.setSize(width, height);
        }

        function animate() {
            requestAnimationFrame(animate);
            if(controls) controls.update();
            if(webglRenderer && scene && camera) webglRenderer.render(scene, camera);
        }

        // --- Button Handlers ---

        function handleResetView() {
            // ... (keep this function exactly as before)
             if (!controls) return;
            controls.reset();
            console.log("View Reset");
        }

        function handleFitView() {
            // ... (keep this function exactly as before)
            if (!loadedModel || !controls || !camera) return;

            const box = new THREE.Box3().setFromObject(loadedModel);
            const size = box.getSize(new THREE.Vector3());
            const center = box.getCenter(new THREE.Vector3());

            const maxSize = Math.max(size.x, size.y, size.z);
            const fitHeightDistance = maxSize / (2 * Math.atan(Math.PI * camera.fov / 360));
            const fitWidthDistance = fitHeightDistance / camera.aspect;
            const distance = 1.2 * Math.max(fitHeightDistance, fitWidthDistance);

            const direction = controls.target.clone().sub(camera.position).normalize().multiplyScalar(distance);

            camera.position.copy(center).sub(direction);
            controls.target.copy(center);
            controls.update();
            console.log("Fit model to view");
        }

        function getExportDimensions() {
            const width = parseInt(exportWidthInput.value, 10);
            const height = parseInt(exportHeightInput.value, 10);

            if (isNaN(width) || isNaN(height) || width <= 0 || height <= 0) {
                alert("Please enter valid positive numbers for width and height.");
                return null;
            }
             if (!loadedModel) {
                alert("Load a model before exporting!");
                return null;
            }
            return { width, height };
        }

        function handleExportPNG() {
            const dims = getExportDimensions();
            if (dims) {
                exportHighResPNG(dims.width, dims.height);
            }
        }

        function handleExportSVG() {
            const dims = getExportDimensions();
            if (dims) {
                exportSVG(dims.width, dims.height);
            }
        }

        // --- Export Functions ---

        function exportHighResPNG(width, height) {
            console.log(`Exporting PNG at ${width}x${height}...`);
            setExportingState(true, 'Exporting PNG...');

            setTimeout(() => {
                let dataURL = '';
                const originalSize = new THREE.Vector2();
                webglRenderer.getSize(originalSize);
                const originalAspect = camera.aspect;
                const originalBackground = scene.background;
                const originalClearAlpha = webglRenderer.getClearAlpha();

                try {
                    scene.background = null;
                    webglRenderer.setClearAlpha(0);
                    webglRenderer.setSize(width, height, false);
                    camera.aspect = width / height;
                    camera.updateProjectionMatrix();
                    webglRenderer.render(scene, camera); // Render once at high-res
                    dataURL = webglRenderer.domElement.toDataURL('image/png');

                    if (dataURL && dataURL !== 'data:,') {
                       const link = document.createElement('a');
                        link.download = `model-view-${width}x${height}-transparent.png`;
                        link.href = dataURL;
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                        console.log("PNG Export download initiated.");
                    } else {
                         throw new Error("Generated PNG Data URL is empty or invalid.");
                    }

                } catch (error) {
                    console.error("PNG Export failed:", error);
                    alert(`PNG Export failed. Resolution might be too high or another error occurred.\n\nError: ${error.message}`);
                } finally {
                    // Restore everything ALWAYS
                    webglRenderer.setSize(originalSize.x, originalSize.y, false);
                    camera.aspect = originalAspect;
                    camera.updateProjectionMatrix();
                    scene.background = originalBackground;
                    webglRenderer.setClearAlpha(originalClearAlpha);
                    // Re-render view at original size
                    requestAnimationFrame(() => {
                         if(webglRenderer && scene && camera) webglRenderer.render(scene, camera);
                    });
                    setExportingState(false);
                    console.log("PNG Export process finished.");
                }
            }, 50);
        }

        function exportSVG(width, height) {
            console.log(`Exporting SVG at ${width}x${height}...`);
            setExportingState(true, 'Exporting SVG...');

            setTimeout(() => {
                let url = null; // For Object URL cleanup
                try {
                    // Create a temporary SVGRenderer instance
                    svgRenderer = new SVGRenderer();
                    svgRenderer.setSize(width, height);
                    svgRenderer.overdraw = 0; // Helps with cleaner output, less z-fighting visual artifacts

                    // Render the scene using the SVGRenderer
                    // Note: Background color is ignored by SVGRenderer, it's inherently transparent
                    svgRenderer.render(scene, camera);

                    // Get the SVG data as a string
                    const svgData = svgRenderer.domElement.outerHTML;

                    if (!svgData || svgData.length < 100) { // Basic check for valid SVG content
                        throw new Error("Generated SVG data seems empty or invalid.");
                    }

                    // Create a Blob and trigger download
                    const blob = new Blob([svgData], { type: 'image/svg+xml;charset=utf-8' });
                    url = URL.createObjectURL(blob);

                    const link = document.createElement('a');
                    link.download = `model-view-${width}x${height}.svg`;
                    link.href = url;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    console.log("SVG Export download initiated.");

                } catch (error) {
                    console.error("SVG Export failed:", error);
                    alert(`SVG Export failed. The model might be too complex for SVGRenderer or another error occurred.\n\nError: ${error.message}`);
                } finally {
                    // Cleanup
                    if (url) {
                        URL.revokeObjectURL(url); // Release the object URL memory
                    }
                    svgRenderer = null; // Allow the temporary renderer to be garbage collected
                    setExportingState(false);
                    console.log("SVG Export process finished.");
                }
            }, 50); // Small delay for UI update
        }


        // Helper to manage exporting state and button disabling
        function setExportingState(isExporting, message = 'Exporting...') {
            exportingIndicator.innerHTML = `${message}<br><small>(May take a moment or freeze)</small>`;
            exportingIndicator.style.display = isExporting ? 'block' : 'none';
            const isModelLoaded = !!loadedModel;
            fileInput.disabled = isExporting;
            exportPngButton.disabled = isExporting || !isModelLoaded;
            exportSvgButton.disabled = isExporting || !isModelLoaded;
            resetViewButton.disabled = isExporting || !isModelLoaded;
            fitViewButton.disabled = isExporting || !isModelLoaded;
            exportWidthInput.disabled = isExporting;
            exportHeightInput.disabled = isExporting;
        }


        // --- Start the application ---
        init();

    </script>
</body>
</html>