class AppRouter {
    constructor() {
        this.sidebar = document.getElementById('sidebar');
        this.sidebarToggle = document.getElementById('sidebarToggle');
        this.moduleContent = document.getElementById('moduleContent');
        this.welcomeSection = document.getElementById('welcomeSection');
        this.navLinks = document.querySelectorAll('.nav-link[data-module]');
        
        this.init();
    }

    init() {
        console.log('✅ AppRouter initialized');

        // === Sidebar toggle ===
        if (this.sidebarToggle) {
            this.sidebarToggle.addEventListener('click', () => {
                this.sidebar.classList.toggle('collapsed');
            });
        }

        // === Gán sự kiện click cho các link ===
        this.navLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                this.handleNavigation(link);
            });
        });

        // === Lắng nghe thay đổi hash ===
        window.addEventListener('hashchange', () => {
            this.handleHashChange();
        });

        // === Xử lý hash khi load trang ===
        this.handleHashChange();
    }

    handleHashChange() {
        const fullHash = window.location.hash.replace('#', '');

        if (fullHash) {
            // Tách module name và tham số
            const [moduleName, ...params] = fullHash.split('?');
            const fullPath = params.length > 0 ? `${moduleName}?${params.join('?')}` : moduleName;

            const link = document.querySelector(`[data-module="${moduleName}"]`);
            if (link) {
                this.handleNavigation(link, fullPath);
            } else {
                // Nếu không tìm thấy link chính xác, thử với module cha
                const parentModule = moduleName.split('/')[0];
                const parentLink = document.querySelector(`[data-module="${parentModule}"]`);
                if (parentLink) {
                    this.handleNavigation(parentLink, fullPath);
                }
            }
        } else {
            // ✅ LUÔN xóa active khỏi tất cả menu khi không có hash
            this.navLinks.forEach(item => item.classList.remove('active'));
            
            // ❌ Không tự động load module mặc định nữa
            // ✅ Hiện phần welcome (màn hình chào mừng)
            if (this.welcomeSection) {
                this.welcomeSection.style.display = 'block';
            }
            if (this.moduleContent) {
                this.moduleContent.style.display = 'none';
            }

            console.log('👋 Không có hash — hiển thị trang chào mừng.');
        }
    }

    handleNavigation(link, fullPath = null) {
        // Xóa class active của tất cả menu
        this.navLinks.forEach(item => item.classList.remove('active'));

        // Thêm class active cho menu được click
        link.classList.add('active');

        // Lấy đường dẫn module
        const modulePath = fullPath || link.dataset.module;
        console.log('➡️ Đang tải module:', modulePath);

        // Nếu hash hiện tại khác thì mới cập nhật (tránh loop)
        if (window.location.hash.replace('#', '') !== modulePath) {
            window.location.hash = modulePath;
        }

        // Ẩn phần chào mừng
        if (this.welcomeSection) this.welcomeSection.style.display = 'none';

        // Hiện phần nội dung module
        if (this.moduleContent) this.moduleContent.style.display = 'block';

        // Tải nội dung module
        this.loadModuleContent(modulePath);
    }

    loadContentViaIframe(url) {
    const timestamp = new Date().getTime(); // thêm dấu thời gian tránh cache
    this.moduleContent.innerHTML = `
        <div class="iframe-container">
            <iframe src="${url}?t=${timestamp}"
                    style="width: 100%; height: 700px; border: none; border-radius: 8px;"
                    onload="console.log('✅ Module loaded successfully')"
                    onerror="console.error('❌ Failed to load module')">
            </iframe>
        </div>
    `;
}



    showModule(modulePath) {
        // Ẩn phần welcome
        if (this.welcomeSection) this.welcomeSection.style.display = 'none';

        // Hiện vùng nội dung
        if (this.moduleContent) this.moduleContent.style.display = 'block';

        // Load nội dung module
        this.loadModuleContent(modulePath);
    }

    loadModuleContent(modulePath) {
        // Tách module name và query string
        const [moduleName, queryString] = modulePath.split('?');
        
        const moduleUrls = {
            'quanlytoanha': '../qlphong/toanha.php',
            'quanlytoanha/quanlyphong': '../qlphong/phong.php',
            'quanlysinhvien': '../qlsinhvien/sinhvien.php',
            'quanlytaichinh': '../qltaichinh/taichinh.php',
            'quanlyvipham': '../qlnoiquy/vipham.html',
            'quanlyhopdong': '../qlhopdong/hopdong.php',
            'thongke': '../thongke/tkphong.html'
        };

        let url = moduleUrls[moduleName];
        
        // Thêm query string nếu có
        if (queryString && url) {
            url += (url.includes('?') ? '&' : '?') + queryString;
        }

        console.log('📂 Loading URL:', url);

        if (url) {
            this.moduleContent.innerHTML = `
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3 text-muted">Đang tải ${moduleName}...</p>
                </div>
            `;

            this.loadContentViaIframe(url);
        } else {
            this.moduleContent.innerHTML = `
                <div class="alert alert-danger mt-5 text-center">
                    <strong>Lỗi:</strong> Không tìm thấy module "${moduleName}".
                </div>
            `;
        }
    }

    loadContentViaIframe(url) {
        this.moduleContent.innerHTML = `
            <div class="iframe-container">
                <iframe src="${url}"
                        style="width: 100%; height: 700px; border: none; border-radius: 8px;"
                        onload="console.log('✅ Module loaded successfully')"
                        onerror="console.error('❌ Failed to load module')">
                </iframe>
            </div>
        `;
    }
}

// Khởi tạo router toàn cục để có thể gọi từ iframe
document.addEventListener('DOMContentLoaded', () => {
    window.app = new AppRouter();
});

// --- FIX chuẩn: Khôi phục trạng thái đúng khi quay lại (Back/Forward) ---
window.addEventListener('pageshow', (event) => {
    const navType = performance.getEntriesByType('navigation')[0]?.type;
    if (event.persisted || navType === 'back_forward') {
        console.log('♻️ Trang được khôi phục từ bfcache — đồng bộ lại menu active');
        if (window.app) {
            // ✅ LUÔN xóa tất cả active class trước
            window.app.navLinks.forEach(link => link.classList.remove('active'));
            // Đặt lại trạng thái active dựa vào hash hiện tại
            setTimeout(() => window.app.handleHashChange(), 100);
        }
    }
});

