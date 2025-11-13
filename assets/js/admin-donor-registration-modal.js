/**
 * Admin Donor Registration Modal Handler
 * Manages the admin-only donor registration modal flow
 * Step 1: Personal Data -> Step 2: Medical History -> Credentials Modal
 */

(function() {
    'use strict';

    // API paths relative to dashboard location (public/Dashboards/)
    const API_BASE = '../api';
    const CONTENT_API = `${API_BASE}/admin-donor-registration-content.php`;
    const SUBMIT_API = `${API_BASE}/admin-donor-registration-submit.php`;

    let currentStep = 1;
    let currentDonorId = null;
    let modalInstance = null;

    /**
     * Execute inline scripts from dynamically injected HTML.
     * Browsers do not automatically run <script> tags added via innerHTML,
     * so we clone them into the document head in sequence.
     */
    function executeInlineScripts(container) {
        if (!container) return;
        const scripts = container.querySelectorAll('script');
        scripts.forEach((script) => {
            const type = (script.type || '').trim().toLowerCase();
            // Skip non-executable script types (e.g., application/json templates)
            if (type && type !== 'text/javascript' && type !== 'application/javascript' && type !== 'module') {
                return;
            }

            const newScript = document.createElement('script');
            if (script.type) {
                newScript.type = script.type;
            }
            if (script.src) {
                newScript.src = script.src;
                newScript.async = false;
            } else {
                newScript.textContent = script.textContent;
            }

            // Preserve additional attributes such as data-* if present
            for (const attr of script.attributes) {
                if (attr.name === 'type' || attr.name === 'src') continue;
                newScript.setAttribute(attr.name, attr.value);
            }

            document.head.appendChild(newScript);
        });
    }

    // Initialize modal when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        const modalElement = document.getElementById('adminDonorRegistrationModal');
        if (modalElement) {
            modalInstance = new bootstrap.Modal(modalElement, {
                backdrop: 'static',
                keyboard: false
            });

            // Reset modal when hidden
            modalElement.addEventListener('hidden.bs.modal', function() {
                resetModal();
            });

            // Handle close button
            const closeBtn = document.getElementById('adminRegistrationCloseBtn');
            if (closeBtn) {
                closeBtn.addEventListener('click', function() {
                    if (confirm('Are you sure you want to cancel? All progress will be lost.')) {
                        resetModal();
                        modalInstance.hide();
                    }
                });
            }
        }
    });

    /**
     * Open the admin donor registration modal
     */
    window.openAdminDonorRegistrationModal = function() {
        if (!modalInstance) {
            console.error('Admin donor registration modal not initialized');
            return;
        }

        // Reset and load step 1
        currentStep = 1;
        currentDonorId = null;
        window.__adminDonorRegistrationFlow = true;
        loadStep(1);
        modalInstance.show();
    };

    /**
     * Load step content
     */
    function loadStep(step) {
        const modalBody = document.getElementById('adminRegistrationModalBody');
        const modalFooter = document.getElementById('adminRegistrationModalFooter');
        const modalTitle = document.getElementById('modalStepTitle');

        if (!modalBody) {
            console.error('Modal body not found');
            return;
        }

        // Show loading state
        modalBody.innerHTML = `
            <div class="text-center py-5">
                <div class="spinner-border text-danger" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-3 text-muted">Loading step ${step}...</p>
            </div>
        `;
        modalFooter.style.display = 'none';

        // Update title
        if (modalTitle) {
            if (step === 1) {
                modalTitle.textContent = 'Register New Donor - Personal Data';
            } else if (step === 2) {
                modalTitle.textContent = 'Register New Donor - Medical History';
            }
        }

        // Build API URL
        let url = `${CONTENT_API}?step=${step}`;
        if (step === 2 && currentDonorId) {
            url += `&donor_id=${encodeURIComponent(currentDonorId)}`;
        }

        // Load content
        fetch(url)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    modalBody.innerHTML = data.html;
                    modalFooter.style.display = 'block';
                    executeInlineScripts(modalBody);

                    // Initialize step-specific functionality
                    if (step === 1) {
                        // Wait a bit for the HTML to be fully parsed
                        setTimeout(() => {
                            initializePersonalDataStep();
                        }, 50);
                    } else if (step === 2) {
                        // Allow inline scripts to register global helpers before initialization
                        setTimeout(() => {
                            if (typeof window.generateAdminMedicalHistoryQuestions === 'function') {
                                try {
                                    window.generateAdminMedicalHistoryQuestions();
                                } catch (err) {
                                    console.error('Error generating medical history questions:', err);
                                }
                            }
                            initializeMedicalHistoryStep();
                        }, 50);
                    }
                } else {
                    throw new Error(data.error || 'Failed to load step content');
                }
            })
            .catch(error => {
                console.error('Error loading step:', error);
                modalBody.innerHTML = `
                    <div class="alert alert-danger">
                        <h5>Error Loading Form</h5>
                        <p>${error.message}</p>
                        <button class="btn btn-secondary" onclick="window.openAdminDonorRegistrationModal()">Try Again</button>
                    </div>
                `;
            });
    }

    /**
     * Initialize Personal Data step (Step 1)
     */
    function initializePersonalDataStep() {
        const form = document.getElementById('adminDonorPersonalDataForm');
        if (!form) {
            console.error('Personal data form not found');
            return;
        }

        // Handle form submission
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            submitPersonalData();
        });

        // Bind age auto-calculation (scripts inside injected HTML don't execute)
        const birthdateInput = document.getElementById('birthdate');
        const ageInput = document.getElementById('age');
        const computeAge = () => {
            if (!birthdateInput || !ageInput || !birthdateInput.value) {
                if (ageInput) ageInput.value = '';
                return;
            }
            const birthdate = new Date(birthdateInput.value);
            if (isNaN(birthdate.getTime())) {
                ageInput.value = '';
                return;
            }
            const today = new Date();
            let age = today.getFullYear() - birthdate.getFullYear();
            const m = today.getMonth() - birthdate.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < birthdate.getDate())) {
                age--;
            }
            ageInput.value = (age >= 0 && Number.isFinite(age)) ? age : '';
        };
        if (birthdateInput) {
            birthdateInput.addEventListener('change', computeAge);
            birthdateInput.addEventListener('input', computeAge);
            // Compute once if a value is already present (e.g., prefill)
            computeAge();
        }

        // Initialize PSGC address autofill (same as mobile PWA)
        // Scripts in injected HTML don't execute, so we need to run it manually
        initializePSGCAddressAutofill();

        // Initialize section navigation
        initializeSectionNavigation();
    }

    /**
     * Initialize PSGC Address Autofill (same as mobile PWA)
     * Uses Philippine Standard Geographic Code API with datalist elements
     */
    function initializePSGCAddressAutofill() {
        try {
            const provinceInput = document.getElementById('province_city');
            const municipalityInput = document.getElementById('town_municipality');
            const barangayInput = document.getElementById('barangay');
            const zipInput = document.getElementById('zip_code');

            const provinceList = document.getElementById('provinceList');
            const municipalityList = document.getElementById('municipalityList');
            const barangayList = document.getElementById('barangayList');

            if (!provinceInput || !municipalityInput || !barangayInput) {
                console.warn('Address inputs not found for PSGC autofill');
                return;
            }

            // Caches for lookups
            let provinces = [];
            let muniByProvCode = {};
            let brgyByCityMuniCode = {};

            // Helper: cached fetch with localStorage
            async function cachedFetch(url, cacheKey, ttlMinutes = 1440) {
                try {
                    const now = Date.now();
                    const cached = localStorage.getItem(cacheKey);
                    if (cached) {
                        const { savedAt, data } = JSON.parse(cached);
                        if (now - savedAt < ttlMinutes * 60 * 1000) {
                            return data;
                        }
                    }
                    const res = await fetch(url);
                    if (!res.ok) throw new Error('Network error');
                    const data = await res.json();
                    localStorage.setItem(cacheKey, JSON.stringify({ savedAt: now, data }));
                    return data;
                } catch (e) {
                    console.error('cachedFetch error for', url, e);
                    return null;
                }
            }

            // Load provinces (plus HUCs)
            async function loadProvinces() {
                const provs = await cachedFetch('https://psgc.gitlab.io/api/provinces/', 'psgc_provinces');
                const hucs = await cachedFetch('https://psgc.gitlab.io/api/huc/', 'psgc_huc');

                provinces = [];
                if (Array.isArray(provs)) provs.forEach(p => provinces.push({ name: p.name, code: p.code }));
                if (Array.isArray(hucs)) hucs.forEach(c => provinces.push({ name: c.name, code: c.code, isHUC: true }));

                if (provinceList) {
                    provinceList.innerHTML = '';
                    provinces
                        .sort((a, b) => a.name.localeCompare(b.name))
                        .forEach(p => {
                            const opt = document.createElement('option');
                            opt.value = p.name;
                            opt.label = p.name;
                            provinceList.appendChild(opt);
                        });
                }
            }

            function findProvinceByName(name) {
                if (!name) return null;
                const n = name.trim().toLowerCase();
                return provinces.find(p => p.name.toLowerCase() === n) || null;
            }

            async function loadMunicipalitiesForProvinceName(provinceName) {
                if (municipalityList) municipalityList.innerHTML = '';
                if (barangayList) barangayList.innerHTML = '';
                if (barangayInput) barangayInput.value = '';
                if (zipInput) zipInput.value = '';

                const prov = findProvinceByName(provinceName);
                if (!prov) return;

                if (prov.isHUC) {
                    muniByProvCode[prov.code] = [{ name: prov.name, code: prov.code, isHUC: true }];
                } else if (!muniByProvCode[prov.code]) {
                    const url = `https://psgc.gitlab.io/api/provinces/${prov.code}/cities-municipalities/`;
                    const data = await cachedFetch(url, `psgc_muni_${prov.code}`);
                    if (Array.isArray(data)) {
                        muniByProvCode[prov.code] = data.map(m => ({ name: m.name, code: m.code }));
                    } else {
                        muniByProvCode[prov.code] = [];
                    }
                }

                if (municipalityList) {
                    (muniByProvCode[prov.code] || [])
                        .sort((a, b) => a.name.localeCompare(b.name))
                        .forEach(m => {
                            const opt = document.createElement('option');
                            opt.value = m.name;
                            opt.label = m.name;
                            municipalityList.appendChild(opt);
                        });
                }
            }

            function findMunicipalityByName(provinceName, muniName) {
                const prov = findProvinceByName(provinceName);
                if (!prov) return null;
                const list = muniByProvCode[prov.code] || [];
                const n = (muniName || '').trim().toLowerCase();
                return list.find(m => m.name.toLowerCase() === n) || null;
            }

            async function loadBarangaysForMunicipality(provinceName, muniName) {
                if (barangayList) barangayList.innerHTML = '';
                const muni = findMunicipalityByName(provinceName, muniName);
                if (!muni) return;

                if (!brgyByCityMuniCode[muni.code]) {
                    const url = `https://psgc.gitlab.io/api/cities-municipalities/${muni.code}/barangays/`;
                    const data = await cachedFetch(url, `psgc_brgy_${muni.code}`);
                    if (Array.isArray(data)) {
                        brgyByCityMuniCode[muni.code] = data.map(b => b.name);
                    } else {
                        brgyByCityMuniCode[muni.code] = [];
                    }
                }

                if (barangayList) {
                    (brgyByCityMuniCode[muni.code] || [])
                        .sort((a, b) => a.localeCompare(b))
                        .forEach(name => {
                            const opt = document.createElement('option');
                            opt.value = name;
                            opt.label = name;
                            barangayList.appendChild(opt);
                        });
                }
            }

            // Wire up events
            if (provinceInput) {
                provinceInput.addEventListener('change', () => {
                    loadMunicipalitiesForProvinceName(provinceInput.value);
                });
            }
            if (municipalityInput) {
                municipalityInput.addEventListener('change', () => {
                    loadBarangaysForMunicipality(provinceInput.value, municipalityInput.value);
                });
            }

            // Initialize
            loadProvinces().then(() => {
                if (provinceInput && provinceInput.value) {
                    loadMunicipalitiesForProvinceName(provinceInput.value).then(() => {
                        if (municipalityInput && municipalityInput.value) {
                            loadBarangaysForMunicipality(provinceInput.value, municipalityInput.value);
                        }
                    });
                }
            });

            // Optional: ZIP code autofill (best-effort, uses community dataset)
            if (zipInput) {
                // ZIP code autofill using community dataset
                (async function initializeZipAutofill() {
                    const ZIP_DATA_URL = 'https://raw.githubusercontent.com/erwinsie/ph-zipcodes/master/zipcodes.json';
                    let zipIndex = null;

                    async function loadZipDataset() {
                        try {
                            const cacheKey = 'ph_zipcodes_dataset_v1';
                            const now = Date.now();
                            const cached = localStorage.getItem(cacheKey);
                            if (cached) {
                                const { savedAt, data } = JSON.parse(cached);
                                if (now - savedAt < 7 * 24 * 60 * 60 * 1000) { // 7 days
                                    return data;
                                }
                            }
                            const res = await fetch(ZIP_DATA_URL, { cache: 'force-cache' });
                            if (!res.ok) throw new Error('Zip dataset fetch failed');
                            const data = await res.json();
                            localStorage.setItem(cacheKey, JSON.stringify({ savedAt: now, data }));
                            return data;
                        } catch (e) {
                            console.warn('Postal dataset unavailable:', e.message);
                            return null;
                        }
                    }

                    function buildZipIndex(raw) {
                        const idx = { exact: {}, byCity: {} };
                        if (!Array.isArray(raw)) return idx;
                        raw.forEach(row => {
                            const province = (row.province || row.Province || '').trim().toLowerCase();
                            const city = (row.city || row.municipality || row.City || '').trim().toLowerCase();
                            const barangay = (row.barangay || row.Barangay || '').trim().toLowerCase();
                            const zip = (row.zipcode || row.ZipCode || row.zip || '').toString().trim();
                            if (!zip) return;
                            if (province && city && barangay) {
                                idx.exact[`${province}|${city}|${barangay}`] = zip;
                            }
                            if (province && city && !idx.byCity[`${province}|${city}`]) {
                                idx.byCity[`${province}|${city}`] = zip; // fallback zip at city level
                            }
                        });
                        return idx;
                    }

                    function tryFillPostal() {
                        if (!zipIndex) return;
                        const p = (provinceInput?.value || '').trim().toLowerCase();
                        const m = (municipalityInput?.value || '').trim().toLowerCase();
                        const b = (barangayInput?.value || '').trim().toLowerCase();

                        let found = null;
                        if (p && m && b) {
                            found = zipIndex.exact[`${p}|${m}|${b}`] || null;
                        }
                        if (!found && p && m) {
                            found = zipIndex.byCity[`${p}|${m}`] || null;
                        }
                        if (found && zipInput && !zipInput.value) {
                            zipInput.value = found;
                            // Add visual feedback when zipcode is auto-filled
                            zipInput.style.backgroundColor = '#d4edda';
                            zipInput.style.borderColor = '#28a745';
                            setTimeout(() => {
                                zipInput.style.backgroundColor = '';
                                zipInput.style.borderColor = '';
                            }, 2000);
                        }
                    }

                    const dataset = await loadZipDataset();
                    if (dataset) {
                        zipIndex = buildZipIndex(dataset);
                        // Add both 'change' and 'input' event listeners for better responsiveness
                        if (provinceInput) {
                            provinceInput.addEventListener('change', tryFillPostal);
                            provinceInput.addEventListener('input', () => {
                                // Debounce input events to avoid too many calls
                                clearTimeout(window.zipFillTimeout);
                                window.zipFillTimeout = setTimeout(tryFillPostal, 500);
                            });
                        }
                        if (municipalityInput) {
                            municipalityInput.addEventListener('change', tryFillPostal);
                            municipalityInput.addEventListener('input', () => {
                                clearTimeout(window.zipFillTimeout);
                                window.zipFillTimeout = setTimeout(tryFillPostal, 500);
                            });
                        }
                        if (barangayInput) {
                            barangayInput.addEventListener('change', tryFillPostal);
                            barangayInput.addEventListener('input', () => {
                                clearTimeout(window.zipFillTimeout);
                                window.zipFillTimeout = setTimeout(tryFillPostal, 500);
                            });
                        }
                        // Try on load in case fields already filled
                        tryFillPostal();
                    }
                })();
            }
        } catch (err) {
            console.error('PSGC address autofill initialization error:', err);
        }
        
        // Initialize ZIP Code autosuggest using the address API
        try {
            const zipInput = document.getElementById('zip_code');
            const zipSuggestions = document.getElementById('zipCodeSuggestions');
            const provinceInput = document.getElementById('province_city');
            const municipalityInput = document.getElementById('town_municipality');
            const barangayInput = document.getElementById('barangay');
            
            if (zipInput && zipSuggestions) {
                const API = '../api/suggest-address.php';
                
                function debounce(fn, ms) {
                    let t;
                    return (...args) => {
                        clearTimeout(t);
                        t = setTimeout(() => fn.apply(this, args), ms);
                    };
                }
                
                function makeItem(text, zipCode, onClick) {
                    const a = document.createElement('a');
                    a.href = '#';
                    a.className = 'list-group-item list-group-item-action';
                    a.innerHTML = `<strong>${zipCode}</strong> - ${text}`;
                    a.onclick = (e) => {
                        e.preventDefault();
                        onClick();
                        hideSuggestions();
                    };
                    return a;
                }
                
                function hideSuggestions() {
                    if (zipSuggestions) {
                        zipSuggestions.style.display = 'none';
                        zipSuggestions.innerHTML = '';
                    }
                }
                
                function showSuggestions() {
                    if (zipSuggestions) {
                        zipSuggestions.style.display = 'block';
                    }
                }
                
                const debouncedSuggest = debounce((query) => {
                    if (!query || query.length < 1) {
                        hideSuggestions();
                        return;
                    }
                    
                    // Check if query is a zipcode (4 digits)
                    const isZipcodeQuery = /^\d{1,4}$/.test(query);
                    
                    let fullQuery;
                    if (isZipcodeQuery) {
                        // If searching by zipcode, search for "zipcode Philippines" or use address context
                        const parts = [];
                        if (municipalityInput?.value) parts.push(municipalityInput.value);
                        if (provinceInput?.value) parts.push(provinceInput.value);
                        parts.push(query); // Add zipcode
                        parts.push('Philippines');
                        fullQuery = parts.filter(Boolean).join(', ');
                    } else {
                        // Normal location search with context
                        const parts = [];
                        if (query) parts.push(query);
                        if (municipalityInput?.value) parts.push(municipalityInput.value);
                        if (provinceInput?.value) parts.push(provinceInput.value);
                        parts.push('Philippines');
                        fullQuery = parts.filter(Boolean).join(', ');
                    }
                    
                    fetch(`${API}?q=${encodeURIComponent(fullQuery)}`)
                        .then(r => r.json())
                        .then(data => {
                            if (!zipSuggestions) return;
                            
                            zipSuggestions.innerHTML = '';
                            const results = data.results || [];
                            
                            // Filter and show unique zipcodes
                            const seenZipcodes = new Set();
                            const zipcodeResults = [];
                            
                            results.forEach(r => {
                                const addr = r.address || {};
                                const zipcode = addr.postcode || '';
                                
                                // Only show 4-digit zipcodes and avoid duplicates
                                if (zipcode && zipcode.length === 4 && !seenZipcodes.has(zipcode)) {
                                    seenZipcodes.add(zipcode);
                                    zipcodeResults.push({
                                        zipcode: zipcode,
                                        display: r.display_name,
                                        address: addr
                                    });
                                }
                            });
                            
                            // Limit to 8 results
                            zipcodeResults.slice(0, 8).forEach(result => {
                                const item = makeItem(result.display, result.zipcode, () => {
                                    zipInput.value = result.zipcode;
                                    
                                    // Optionally fill other address fields if empty
                                    if (result.address) {
                                        if (result.address.barangay && !barangayInput?.value) {
                                            barangayInput.value = result.address.barangay;
                                        }
                                        if ((result.address.city || result.address.town) && !municipalityInput?.value) {
                                            municipalityInput.value = result.address.city || result.address.town;
                                        }
                                        if ((result.address.province || result.address.state) && !provinceInput?.value) {
                                            provinceInput.value = result.address.province || result.address.state;
                                        }
                                    }
                                    
                                    // Visual feedback
                                    zipInput.style.backgroundColor = '#d4edda';
                                    zipInput.style.borderColor = '#28a745';
                                    setTimeout(() => {
                                        zipInput.style.backgroundColor = '';
                                        zipInput.style.borderColor = '';
                                    }, 1500);
                                });
                                zipSuggestions.appendChild(item);
                            });
                            
                            if (zipcodeResults.length > 0) {
                                showSuggestions();
                            } else {
                                hideSuggestions();
                            }
                        })
                        .catch(() => {
                            hideSuggestions();
                        });
                }, 300);
                
                // Add input event listener for autosuggest
                zipInput.addEventListener('input', (e) => {
                    const value = e.target.value.trim();
                    // Only show suggestions if user is typing (not just numbers)
                    // But also allow searching by zipcode itself
                    if (value.length >= 1) {
                        debouncedSuggest(value);
                    } else {
                        hideSuggestions();
                    }
                });
                
                // Hide suggestions when clicking outside
                document.addEventListener('click', (e) => {
                    if (!e.target.closest('#zip_code') && !e.target.closest('#zipCodeSuggestions')) {
                        hideSuggestions();
                    }
                });
                
                // Hide suggestions on blur (with delay to allow click on suggestion)
                zipInput.addEventListener('blur', () => {
                    setTimeout(() => hideSuggestions(), 200);
                });
            }
        } catch (err) {
            console.error('ZIP Code autosuggest initialization error:', err);
        }
    }


    /**
     * Initialize section navigation for Personal Data form
     */
    function initializeSectionNavigation() {
        // Override the navigation functions to use our handlers
        window.adminRegistrationNextSection = function(currentSection) {
            const currentSectionEl = document.getElementById(`section${currentSection}`);
            if (!currentSectionEl) return;

            // Validate current section
            const inputs = currentSectionEl.querySelectorAll('input[required], select[required]');
            let isValid = true;

            inputs.forEach(input => {
                if (!input.value.trim()) {
                    input.classList.add('is-invalid');
                    isValid = false;
                } else {
                    input.classList.remove('is-invalid');
                }
            });

            if (!isValid) {
                alert('Please fill in all required fields before proceeding.');
                return;
            }

            // Hide current section
            currentSectionEl.classList.remove('active');

            // Show next section
            if (currentSection < 5) {
                const nextSection = currentSection + 1;
                const nextSectionEl = document.getElementById(`section${nextSection}`);
                if (nextSectionEl) {
                    nextSectionEl.classList.add('active');
                    updateStepIndicator(nextSection);
                }
            }
        };

        window.adminRegistrationPrevSection = function(currentSection) {
            const currentSectionEl = document.getElementById(`section${currentSection}`);
            if (!currentSectionEl) return;

            // Hide current section
            currentSectionEl.classList.remove('active');

            // Show previous section
            if (currentSection > 1) {
                const prevSection = currentSection - 1;
                const prevSectionEl = document.getElementById(`section${prevSection}`);
                if (currentSectionEl) {
                    prevSectionEl.classList.add('active');
                    updateStepIndicator(prevSection);
                }
            }
        };
    }

    /**
     * Submit Personal Data (Step 1)
     */
    function submitPersonalData() {
        const form = document.getElementById('adminDonorPersonalDataForm');
        if (!form) {
            console.error('Personal data form not found');
            return;
        }

        // Validate all sections
        const allInputs = form.querySelectorAll('input[required], select[required]');
        let isValid = true;

        allInputs.forEach(input => {
            if (!input.value.trim()) {
                input.classList.add('is-invalid');
                isValid = false;
            } else {
                input.classList.remove('is-invalid');
            }
        });

        if (!isValid) {
            alert('Please fill in all required fields before submitting.');
            // Show first section with error
            for (let i = 1; i <= 5; i++) {
                const section = document.getElementById(`section${i}`);
                if (section) {
                    const invalidInputs = section.querySelectorAll('.is-invalid');
                    if (invalidInputs.length > 0) {
                        // Hide all sections
                        for (let j = 1; j <= 5; j++) {
                            const s = document.getElementById(`section${j}`);
                            if (s) s.classList.remove('active');
                        }
                        // Show section with error
                        section.classList.add('active');
                        updateStepIndicator(1, i);
                        break;
                    }
                }
            }
            return;
        }

        // Show loading state
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn ? submitBtn.innerHTML : '';
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';
        }

        // Prepare form data
        const formData = new FormData(form);
        formData.append('step', '1');

        // Submit to API
        fetch(SUBMIT_API, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Store donor ID
                currentDonorId = data.donor_id;
                currentStep = 2;

                // Load step 2
                loadStep(2);
            } else {
                throw new Error(data.error || 'Failed to save personal data');
            }
        })
        .catch(error => {
            console.error('Error submitting personal data:', error);
            alert('Error: ' + error.message);
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        });
    }

    /**
     * Initialize Medical History step (Step 2)
     */
    function initializeMedicalHistoryStep() {
        // The medical history form is loaded from the existing admin MH content
        // It should have its own initialization scripts
        // We just need to handle the form submission to our API

        const form = document.getElementById('modalMedicalHistoryForm');
        if (!form) {
            console.error('Medical history form not found');
            return;
        }

        // Update form action to use our API
        form.action = SUBMIT_API;

        // Add step parameter
        const stepInput = document.createElement('input');
        stepInput.type = 'hidden';
        stepInput.name = 'step';
        stepInput.value = '2';
        form.appendChild(stepInput);

        // Override form submission
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            submitMedicalHistory();
        });

        // The existing MH form has its own navigation and validation
        // We just need to intercept the final submission
    }

    /**
     * Submit Medical History (Step 2)
     */
    function submitMedicalHistory() {
        const form = document.getElementById('modalMedicalHistoryForm');
        if (!form) {
            console.error('Medical history form not found');
            return;
        }

        // Show loading state
        const submitBtn = form.querySelector('button[type="submit"], #modalSubmitButton');
        const originalText = submitBtn ? submitBtn.innerHTML : '';
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
        }

        // Prepare form data
        const formData = new FormData(form);
        formData.append('step', '2');
        formData.append('action', 'admin_complete');

        // Submit to API
        fetch(SUBMIT_API, {
            method: 'POST',
            body: formData
        })
        .then(async (response) => {
            const rawText = await response.text();
            let data;
            try {
                data = rawText ? JSON.parse(rawText) : {};
            } catch (parseErr) {
                console.error('Failed to parse medical history response as JSON:', parseErr, rawText);
                throw new Error(`Unexpected response (status ${response.status}): ${rawText.substring(0, 300)}`);
            }

            if (!response.ok || data.success === false) {
                const message = data?.error || data?.message || `Request failed with status ${response.status}`;
                throw new Error(message);
            }

            return data;
        })
        .then(data => {
            if (data.success) {
                window.__latestRegisteredDonorId = data.donor_id || null;
                const credentialsModalEl = document.getElementById('mobileCredentialsModal');
                if (credentialsModalEl) {
                    credentialsModalEl.setAttribute('data-donor-id', data.donor_id || '');
                }

                const hasCreds = data.credentials && data.credentials.email && data.credentials.password;
                if (hasCreds) {
                    try {
                        const emailInput = document.getElementById('mobileEmail');
                        const passwordInput = document.getElementById('mobilePassword');
                        if (emailInput && data.credentials.email) {
                            emailInput.value = data.credentials.email;
                        }
                        if (passwordInput && data.credentials.password) {
                            passwordInput.value = data.credentials.password;
                        }
                        const donorNameSpan = document.getElementById('mobileCredentialsDonorName');
                        if (donorNameSpan) {
                            donorNameSpan.textContent = data.donor_name || donorNameSpan.textContent;
                        }
                    } catch (err) {
                        console.warn('Unable to populate credentials modal', err);
                    }
                } else {
                    console.warn('Medical history saved but no credentials were returned');
                }

                // Close registration modal
                if (modalInstance) {
                    modalInstance.hide();
                }

                // Show credentials modal
                if (hasCreds) {
                    setTimeout(() => {
                        showCredentialsModal();
                    }, 300);
                }
            }
        })
        .catch(error => {
            console.error('Error submitting medical history:', error);
            alert('Error: ' + error.message);
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        });
    }

    /**
     * Show credentials modal
     */
    function showCredentialsModal() {
        // Check if credentials modal exists
        const credentialsModal = document.getElementById('mobileCredentialsModal');
        if (credentialsModal) {
            credentialsModal.setAttribute('data-donor-id', window.__latestRegisteredDonorId || credentialsModal.getAttribute('data-donor-id') || '');
            credentialsModal.style.display = '';
            credentialsModal.setAttribute('data-auto-show', 'true');
            const credentialsModalInstance = new bootstrap.Modal(credentialsModal, {
                backdrop: 'static',
                keyboard: false
            });

            credentialsModalInstance.show();
        } else {
            console.error('[Admin Registration] mobileCredentialsModal element not found; skipping credentials step.');
        }
    }

    /**
     * Update step indicator
     */
    function updateStepIndicator(subStep) {
        // Update 5-step indicator within Personal Data form
        for (let i = 1; i <= 5; i++) {
            const stepEl = document.getElementById(`step${i}`);
            const lineEl = document.getElementById(`line${i}-${i+1}`);

            if (stepEl) {
                if (i < subStep) {
                    stepEl.classList.remove('active', 'inactive');
                    stepEl.classList.add('completed');
                } else if (i === subStep) {
                    stepEl.classList.remove('inactive', 'completed');
                    stepEl.classList.add('active');
                } else {
                    stepEl.classList.remove('active', 'completed');
                    stepEl.classList.add('inactive');
                }
            }

            if (lineEl) {
                if (i < subStep) {
                    lineEl.classList.add('active');
                } else {
                    lineEl.classList.remove('active');
                }
            }
        }
    }

    /**
     * Reset modal state
     */
    function resetModal() {
        currentStep = 1;
        currentDonorId = null;
        window.__adminDonorRegistrationFlow = false;
        const modalBody = document.getElementById('adminRegistrationModalBody');
        if (modalBody) {
            modalBody.innerHTML = `
                <div class="text-center py-5">
                    <div class="spinner-border text-danger" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3 text-muted">Loading registration form...</p>
                </div>
            `;
        }
    }

    // Export function for global access
    window.AdminDonorRegistrationModal = {
        open: openAdminDonorRegistrationModal,
        loadStep: loadStep,
        currentStep: function() { return currentStep; },
        currentDonorId: function() { return currentDonorId; }
    };

})();

