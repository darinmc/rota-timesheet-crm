</div> <!-- dashboard-layout -->

<footer style="text-align:center;padding:15px;margin-top:20px;color:#777;">
    STS Transport System © <?= date('Y') ?>
</footer>

<script>
function toggleSidebar() {
    document.body.classList.toggle("sidebar-collapsed");
    
    // Save the state to localStorage
    const isCollapsed = document.body.classList.contains("sidebar-collapsed");
    localStorage.setItem('sidebarCollapsed', isCollapsed);
}

// Restore sidebar state when page loads
document.addEventListener('DOMContentLoaded', function() {
    const savedState = localStorage.getItem('sidebarCollapsed');
    if (savedState === 'true') {
        document.body.classList.add('sidebar-collapsed');
    }
    
    // Populate mobile menu with sidebar links
    populateMobileMenu();
});

// Mobile menu toggle function
function toggleMobileMenu() {
    const menu = document.getElementById('mobileMenu');
    if (menu.style.display === 'flex') {
        menu.style.display = 'none';
    } else {
        menu.style.display = 'flex';
    }
}

// Populate mobile menu with same links as sidebar
function populateMobileMenu() {
    const sidebarLinks = document.querySelectorAll('.sidebar-nav a');
    const mobileNav = document.querySelector('.mobile-menu-nav');
    
    if (mobileNav && sidebarLinks.length > 0 && mobileNav.children.length === 0) {
        sidebarLinks.forEach(link => {
            const newLink = document.createElement('a');
            newLink.href = link.href;
            newLink.innerHTML = link.innerHTML;
            newLink.onclick = function() {
                document.getElementById('mobileMenu').style.display = 'none';
            };
            mobileNav.appendChild(newLink);
        });
    }
}

// Close mobile menu on window resize if becomes desktop
window.addEventListener('resize', function() {
    if (window.innerWidth > 768) {
        const menu = document.getElementById('mobileMenu');
        if (menu) {
            menu.style.display = 'none';
        }
    }
});

// Auto-remove toast after 4 seconds
setTimeout(() => {
    const toast = document.querySelector('.toast-message');
    if (toast) {
        toast.style.opacity = '0';
        setTimeout(() => toast?.remove(), 500);
    }
}, 4000);

// For touch devices - show tooltip on tap
let tooltipTimeout;
const sidebarLinks = document.querySelectorAll('.sidebar-nav a');

sidebarLinks.forEach(link => {
    link.addEventListener('touchstart', function(e) {
        // Only show tooltip on collapsed sidebar
        if (document.body.classList.contains('sidebar-collapsed')) {
            e.preventDefault();
            
            // Remove any existing tooltips
            document.querySelectorAll('.custom-tooltip-mobile').forEach(t => t.remove());
            
            const tooltipText = this.querySelector('span')?.innerText || this.textContent.trim();
            if (!tooltipText) return;
            
            const tooltip = document.createElement('div');
            tooltip.className = 'custom-tooltip-mobile';
            tooltip.textContent = tooltipText;
            document.body.appendChild(tooltip);
            
            const rect = this.getBoundingClientRect();
            tooltip.style.position = 'fixed';
            tooltip.style.left = (rect.right + 10) + 'px';
            tooltip.style.top = (rect.top + (rect.height / 2) - 15) + 'px';
            tooltip.style.background = '#2c3e50';
            tooltip.style.color = 'white';
            tooltip.style.padding = '6px 12px';
            tooltip.style.borderRadius = '4px';
            tooltip.style.fontSize = '14px';
            tooltip.style.whiteSpace = 'nowrap';
            tooltip.style.zIndex = '10000';
            tooltip.style.pointerEvents = 'none';
            tooltip.style.boxShadow = '0 2px 5px rgba(0,0,0,0.2)';
            
            // Hide tooltip after 2 seconds
            setTimeout(() => tooltip.remove(), 2000);
        }
    });
});
</script>

</body>
</html>