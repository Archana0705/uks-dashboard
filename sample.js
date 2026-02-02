    // ================== MAIN FILTER API FUNCTION WITH PROPER ARRAY HANDLING ==================

    // Load filtered data from analytics_suggestionform_smple
    async function loadFilteredData(filters) {
        try {
            // Build filter conditions - Start with geographic filters
            let filterConditions = {};

            // Geographic filters - only add if not 'ALL'
            if (filters.district && filters.district !== 'ALL') {
                filterConditions.district_id = filters.district;
            }
            if (filters.taluk && filters.taluk !== 'ALL') {
                filterConditions.taluk_id = filters.taluk;
            }
            if (filters.village && filters.village !== 'ALL') {
                filterConditions.village_id = filters.village;
            }
            if (filters.shop && filters.shop !== 'ALL') {
                const shopOption = $('#shop option:selected');
                if (shopOption.length > 0 && shopOption.text()) {
                    filterConditions.shopcode = shopOption.text();
                }
            }

            // Collect scheme filters (type_id values)
            const schemeFilters = [];
            for (let i = 1; i <= 3; i++) {
                const schemeId = filters[`schemeList${i}`];
                if (schemeId && schemeId !== 'ALL') {
                    schemeFilters.push(schemeId);
                }
            }

            console.log("Scheme filters found:", schemeFilters);
            console.log("Scheme logic:", schemeLogic);
            console.log("Total filters:", Object.keys(filterConditions).length);

            // CORRECTED: Proper array handling for multiple schemes
            if (schemeFilters.length > 0) {
                if (schemeLogic === 'OR') {
                    // OR logic: match ANY of the selected schemes
                    // API expects array format directly for OR logic
                    if (schemeFilters.length === 1) {
                        // Single scheme - simple equality
                        filterConditions.type_id = schemeFilters[0];
                    } else {
                        // Multiple schemes - send as array
                        // The API handles array as OR condition
                        filterConditions.type_id = schemeFilters;
                    }
                    console.log("Using OR logic with schemes array:", filterConditions.type_id);
                } else {
                    // AND logic: match ALL selected schemes
                    if (schemeFilters.length === 1) {
                        // Single scheme - simple equality
                        filterConditions.type_id = schemeFilters[0];
                    } else {
                        // For AND logic with multiple schemes:
                        // First, use array format to get records with ANY of the schemes
                        // Then filter client-side for AND condition
                        filterConditions.type_id = schemeFilters;
                        console.log("Using AND logic initial filter with array:", filterConditions.type_id);
                    }
                }
            }

            console.log("Final filter conditions for API:", JSON.stringify(filterConditions, null, 2));

            const payload = {
                "action": "select",
                "_table_name": "analytics.analytics_suggestionform_smple",
                "selected_columns": [
                    "id", "taluk_id", "ufcno", "type_id", "schemetype_id",
                    "suggestion_remarks", "active", "created_by", "created_ts",
                    "updated_by", "updated_ts", "shopcode", "subscheme_id",
                    "type", "scheme_name", "district_id", "district_name",
                    "taluk_name", "village_id", "village_name"
                ],
                "filter_conditions": filterConditions,
                "sort_columns": {
                    "district_name": "asc",
                    "taluk_name": "asc"
                },
                "limit_rows": 3000, // Increased for AND logic filtering
                "offset_rows": 0
            };

            console.log("Loading filtered data with payload:", JSON.stringify(payload, null, 2));
            const response = await makeApiRequest(payload);

            if (response && response.success === 1) {
                const decryptedData = decryptData(response.data);
                let filteredList = decryptedData;

                if (typeof decryptedData === 'string') {
                    try {
                        filteredList = JSON.parse(decryptedData);
                    } catch (parseError) {
                        console.error("Error parsing filtered data:", parseError);
                        filteredList = decryptedData;
                    }
                }

                if (Array.isArray(filteredList)) {
                    console.log(`Initial API returned ${filteredList.length} records`);
                    
                    // Apply AND logic filtering if needed
                    if (schemeLogic === 'AND' && schemeFilters.length > 1) {
                        console.log(`Applying AND logic filtering for ${schemeFilters.length} schemes`);
                        
                        // Create a map to track families and their schemes
                        const familySchemesMap = new Map();
                        
                        // First pass: organize data by ufcno (family identifier)
                        filteredList.forEach(item => {
                            const ufcno = item.ufcno;
                            if (!ufcno) return;
                            
                            if (!familySchemesMap.has(ufcno)) {
                                familySchemesMap.set(ufcno, {
                                    records: [],
                                    schemeIds: new Set()
                                });
                            }
                            
                            const familyData = familySchemesMap.get(ufcno);
                            familyData.records.push(item);
                            familyData.schemeIds.add(String(item.type_id)); // Convert to string for comparison
                        });
                        
                        console.log(`Found ${familySchemesMap.size} unique families`);
                        
                        // Second pass: filter families that have ALL selected schemes
                        const andFilteredList = [];
                        const schemeFilterStrings = schemeFilters.map(id => String(id));
                        
                        familySchemesMap.forEach((familyData, ufcno) => {
                            const hasAllSchemes = schemeFilterStrings.every(schemeId => 
                                familyData.schemeIds.has(schemeId)
                            );
                            
                            if (hasAllSchemes) {
                                // Add all records for this family
                                andFilteredList.push(...familyData.records);
                            }
                        });
                        
                        console.log(`AND logic: ${filteredList.length} initial records -> ${andFilteredList.length} records after filtering`);
                        console.log(`Found ${andFilteredList.length / schemeFilters.length} families with ALL selected schemes`);
                        filteredList = andFilteredList;
                    }
                    
                    API_DATA.filteredData = filteredList;
                    console.log(`Final filtered records: ${filteredList.length}`);
                    return filteredList;
                } else {
                    console.error("Filtered list is not an array:", filteredList);
                    API_DATA.filteredData = [];
                    return [];
                }
            } else {
                console.error("API did not return success: 1 for filtered data");
                console.error("API Response:", response);
                API_DATA.filteredData = [];
                return [];
            }
        } catch (error) {
            console.error('Error loading filtered data:', error);
            API_DATA.filteredData = [];
            return [];
        }
    }

    // ================== SCHEME DESCRIPTION API FUNCTION WITH ARRAY SUPPORT ==================

    // Load scheme descriptions for multiple schemes
    async function loadSchemeDescriptions(schemeIds) {
        try {
            // Build filter conditions
            let filterConditions = {};
            
            // Check if schemeIds is an array or single value
            if (Array.isArray(schemeIds) && schemeIds.length > 0) {
                if (schemeIds.length === 1) {
                    filterConditions.type_id = schemeIds[0];
                } else {
                    // Send as array directly (API expects array format)
                    filterConditions.type_id = schemeIds;
                }
            } else if (schemeIds) {
                // Single scheme ID
                filterConditions.type_id = schemeIds;
            } else {
                console.error("No scheme IDs provided");
                return [];
            }

            // Apply current geographic filters if set
            const district = $('#district').val();
            const taluk = $('#taluk').val();
            const village = $('#village').val();
            const shop = $('#shop').val();

            if (district && district !== 'ALL') {
                filterConditions.district_id = district;
            }
            if (taluk && taluk !== 'ALL') {
                filterConditions.taluk_id = taluk;
            }
            if (village && village !== 'ALL') {
                filterConditions.village_id = village;
            }
            if (shop && shop !== 'ALL') {
                const shopOption = $('#shop option:selected');
                if (shopOption.length > 0 && shopOption.text()) {
                    filterConditions.shopcode = shopOption.text();
                }
            }

            const payload = {
                "action": "select",
                "_table_name": "analytics.analytics_suggestionform_smple",
                "selected_columns": [
                    "district_name", "taluk_name", "village_name",
                    "suggestion_remarks", "created_ts", "shopcode",
                    "scheme_name", "type", "active", "type_id"
                ],
                "filter_conditions": filterConditions,
                "sort_columns": {
                    "created_ts": "desc"
                },
                "limit_rows": 150, // Increased for multiple schemes
                "offset_rows": 0
            };

            console.log("Loading scheme descriptions with payload:", JSON.stringify(payload, null, 2));
            const response = await makeApiRequest(payload);

            if (response && response.success === 1) {
                const decryptedData = decryptData(response.data);
                let descriptions = decryptedData;

                if (typeof decryptedData === 'string') {
                    try {
                        descriptions = JSON.parse(decryptedData);
                    } catch (parseError) {
                        console.error("Error parsing description data:", parseError);
                        descriptions = decryptedData;
                    }
                }

                if (Array.isArray(descriptions)) {
                    console.log(`Loaded ${descriptions.length} descriptions for scheme(s) ${schemeIds}`);
                    return descriptions;
                } else {
                    console.error("Description list is not an array:", descriptions);
                    return [];
                }
            } else {
                console.error("API did not return success: 1 for scheme descriptions");
                console.error("API Response:", response);
                return [];
            }
        } catch (error) {
            console.error('Error loading scheme descriptions:', error);
            return [];
        }
    }

    // ================== TEST FUNCTION TO VERIFY API FORMAT ==================

    // Test function to verify the API accepts array format
    async function testApiArrayFormat() {
        try {
            // Test payload with array type_id
            const testPayload = {
                "action": "select",
                "_table_name": "analytics.analytics_suggestionform_smple",
                "selected_columns": ["id", "type_id", "scheme_name"],
                "filter_conditions": {
                    "type_id": ["625", "604"] // Array format
                },
                "sort_columns": {},
                "limit_rows": 10,
                "offset_rows": 0
            };

            console.log("Testing API with array format:", JSON.stringify(testPayload, null, 2));
            const response = await makeApiRequest(testPayload);
            
            if (response && response.success === 1) {
                console.log("✅ API accepts array format successfully!");
                const decryptedData = decryptData(response.data);
                console.log("Test response data:", decryptedData);
                return true;
            } else {
                console.log("❌ API rejected array format");
                console.log("Response:", response);
                return false;
            }
        } catch (error) {
            console.error("Error testing API array format:", error);
            return false;
        }
    }

    // ================== ENHANCED FILTER SUMMARY FOR ARRAY FORMAT ==================

    // Update filter summary display
    function updateFilterSummary(filters) {
        const summaryDiv = $('#activeFiltersDisplay');
        summaryDiv.empty();

        // Add geographic filters
        if (filters.district !== 'ALL') {
            const districtName = $('#district option:selected').text();
            summaryDiv.append(`<span class="active-filter-badge"><i class="bi bi-geo-alt"></i> District: ${districtName}</span>`);
        }

        if (filters.taluk !== 'ALL') {
            const talukName = $('#taluk option:selected').text();
            summaryDiv.append(`<span class="active-filter-badge"><i class="bi bi-geo-alt"></i> Taluk: ${talukName}</span>`);
        }

        if (filters.village !== 'ALL') {
            const villageName = $('#village option:selected').text();
            summaryDiv.append(`<span class="active-filter-badge"><i class="bi bi-geo-alt"></i> Village: ${villageName}</span>`);
        }

        if (filters.shop !== 'ALL') {
            const shopName = $('#shop option:selected').text();
            summaryDiv.append(`<span class="active-filter-badge"><i class="bi bi-shop"></i> Shop: ${shopName}</span>`);
        }

        // Add demographic filters
        if (filters.gender !== 'ALL') {
            summaryDiv.append(`<span class="active-filter-badge"><i class="bi bi-gender-ambiguous"></i> Gender: ${filters.gender}</span>`);
        }

        if (filters.ageMin !== '18' || filters.ageMax !== '80') {
            summaryDiv.append(`<span class="active-filter-badge"><i class="bi bi-calendar3"></i> Age: ${filters.ageMin}-${filters.ageMax}</span>`);
        }

        // Add scheme filters
        const selectedSchemes = [];
        for (let i = 1; i <= 3; i++) {
            const category = filters[`schemeCategory${i}`];
            const schemeId = filters[`schemeList${i}`];

            if (category !== 'ALL' && schemeId !== 'ALL') {
                const schemeName = $(`#schemeList${i} option:selected`).text();
                const categoryName = $(`#schemeCategory${i} option:selected`).text();
                selectedSchemes.push(`${categoryName}: ${schemeName}`);
            }
        }

        if (selectedSchemes.length > 0) {
            const logicText = schemeLogic === 'AND' ? 'AND' : 'OR';
            const schemesText = selectedSchemes.join(` ${logicText} `);
            summaryDiv.append(`<span class="active-filter-badge" style="background:#f59e0b;"><i class="bi bi-card-checklist"></i> Schemes (${logicText}): ${schemesText}</span>`);
            
            // Show array format info
            summaryDiv.append(`<span class="active-filter-badge" style="background:#28a745;"><i class="bi bi-code"></i> API Format: Array</span>`);
        }

        // Add logic badge
        summaryDiv.append(`<span class="active-filter-badge" style="background:#6c757d;"><i class="bi bi-funnel"></i> Logic: ${schemeLogic}</span>`);
    }

    // ================== ENHANCED INITIALIZATION WITH API TEST ==================

    // Initialize all data
    async function initializeAllData() {
        try {
            console.log("Initializing all data...");

            // Load districts
            await loadDistricts();
            populateDistrictDropdown();

            // Load scheme categories
            await loadSchemeCategories();
            populateSchemeCategoryDropdowns();

            // Load all schemes initially
            await loadAllSchemes();

            // Initialize scheme dropdowns with "ALL" option
            for (let i = 1; i <= 3; i++) {
                populateSchemeDropdown(i, 'ALL');
            }

            // Test API array format
            console.log("Testing API array format compatibility...");
            const apiTestResult = await testApiArrayFormat();
            
            if (apiTestResult) {
                console.log("✅ API array format test PASSED");
                // Show success message
                $('#activeFiltersDisplay').html(`
                    <span class="active-filter-badge" style="background:#28a745;">
                        <i class="bi bi-check-circle"></i> API Array Format: Supported
                    </span>
                `);
            } else {
                console.log("⚠️ API array format test FAILED - Using fallback method");
                // Show warning message
                $('#activeFiltersDisplay').html(`
                    <span class="active-filter-badge" style="background:#ffc107;">
                        <i class="bi bi-exclamation-triangle"></i> API Array Format: Testing required
                    </span>
                `);
            }

            console.log("All data initialized successfully");

        } catch (error) {
            console.error('Initialization error:', error);

            // Fallback initialization
            populateDistrictDropdown();
            populateSchemeCategoryDropdowns();

            for (let i = 1; i <= 3; i++) {
                $(`#schemeList${i}`).html('<option value="ALL" selected>All Schemes</option>');
            }
        }
    }

    // ================== ENHANCED DEBUGGING FUNCTION ==================

    // Debug function to show current filter state
    function debugFilters() {
        console.log("=== FILTER DEBUG INFO ===");
        
        // Collect all filter values
        const filters = {
            district: $('#district').val(),
            taluk: $('#taluk').val(),
            village: $('#village').val(),
            shop: $('#shop').val(),
            gender: $('#gender').val(),
            schemeLogic: schemeLogic
        };

        // Collect scheme filters
        const schemeFilters = [];
        for (let i = 1; i <= 3; i++) {
            const category = $(`#schemeCategory${i}`).val();
            const schemeId = $(`#schemeList${i}`).val();
            if (schemeId !== 'ALL' && category !== 'ALL') {
                schemeFilters.push({
                    position: i,
                    category: category,
                    schemeId: schemeId,
                    schemeName: $(`#schemeList${i} option:selected`).text()
                });
            }
        }

        console.log("Geographic Filters:", filters);
        console.log("Scheme Filters:", schemeFilters);
        console.log("Scheme Logic:", schemeLogic);
        
        // Build expected API payload
        let expectedFilterConditions = {};
        
        // Add geographic filters
        if (filters.district !== 'ALL') expectedFilterConditions.district_id = filters.district;
        if (filters.taluk !== 'ALL') expectedFilterConditions.taluk_id = filters.taluk;
        if (filters.village !== 'ALL') expectedFilterConditions.village_id = filters.village;
        
        // Add scheme filters as array
        if (schemeFilters.length > 0) {
            const schemeIds = schemeFilters.map(s => s.schemeId);
            expectedFilterConditions.type_id = schemeIds;
        }
        
        console.log("Expected API Filter Conditions:", JSON.stringify(expectedFilterConditions, null, 2));
        
        alert(`Debug Info:\n\nScheme Filters: ${schemeFilters.length}\nScheme Logic: ${schemeLogic}\n\nCheck console for details.`);
    }

    // Add debug button to UI (optional)
    function addDebugButton() {
        const debugBtn = $('<button>')
            .addClass('btn btn-sm btn-outline-info ms-2')
            .html('<i class="bi bi-bug"></i> Debug')
            .click(debugFilters);
        
        $('.justify-content-end').append(debugBtn);
    }

    // ================== MODIFIED EVENT LISTENERS SETUP ==================

    // Set up event listeners
    function setupEventListeners() {
        console.log("Setting up enhanced event listeners");

        // Geographic filters
        $('#district').on('change', handleDistrictChange);
        $('#taluk').on('change', handleTalukChange);
        $('#village').on('change', handleVillageChange);
        $('#shop').on('change', applyFilters);

        // Demographic filters
        $('#gender, #ageMin, #ageMax, #dateFrom, #dateTo').on('change', applyFilters);

        // Scheme category filters
        for (let i = 1; i <= 3; i++) {
            $(`#schemeCategory${i}`).on('change', function () {
                handleSchemeCategoryChange(i);
            });
        }

        // Scheme list filters
        for (let i = 1; i <= 3; i++) {
            $(`#schemeList${i}`).on('change', function () {
                updateSchemeDescriptions();
                applyFilters();
            });
        }

        // Add debug button
        addDebugButton();

        console.log("Event listeners setup complete");
    }

    // Rest of the functions remain the same as in the previous complete script...
    // Only the functions above have been modified for array format support
