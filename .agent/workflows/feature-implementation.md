---
description: Feature implementation checklist - always verify backend AND frontend are complete
---

# Feature Implementation Checklist

When implementing ANY new feature, ALWAYS verify BOTH components exist:

## ✅ Backend Checklist
- [ ] PHP class/function created
- [ ] AJAX handler registered (`add_action('wp_ajax_...')`)
- [ ] Database tables/columns if needed
- [ ] Security: nonce, capability checks, sanitization

## ✅ Frontend Checklist  
- [ ] UI element exists (button, form, link, menu item)
- [ ] User can ACCESS the feature (visible, not hidden)
- [ ] JavaScript handler connected to UI element
- [ ] Localization strings for any messages
- [ ] CSS styling for new elements

## ⚠️ Common Mistakes to Avoid
1. Creating PHP class but NO button/link to trigger it
2. Adding AJAX handler but NO JavaScript to call it
3. Implementing feature in code but NO menu/page to access it
4. Backend returns data but frontend doesn't display it
5. **Removing UI but leaving dead backend code**
6. **Removing backend but leaving broken UI elements**

## 🗑️ When REMOVING a Feature
Remove from BOTH sides completely:

### Frontend Removal
- [ ] Remove UI elements (buttons, forms, menu items)
- [ ] Remove JavaScript handlers and event bindings
- [ ] Remove CSS styles for removed elements
- [ ] Remove localization strings

### Backend Removal
- [ ] Remove PHP class/functions
- [ ] Remove AJAX handler registrations
- [ ] Remove database tables/columns (if safe)
- [ ] Remove includes/requires

**Rule: No orphaned code! Dead code is tech debt.**

## 🔍 Verification Steps
After implementing a feature, ALWAYS:
1. Open browser and navigate to the feature
2. Click/interact with the UI element
3. Verify the action completes successfully
4. Check for proper error handling

**Rule: If you can't click it, it doesn't exist!**
