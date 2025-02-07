function overTab(tabElement) {
    var tabId = tabElement.id.replace("tab_", "content_");

    // 모든 탭과 콘텐츠 숨기기
    $(".tab").removeClass("on");
    $(".tabContent").removeClass("show").addClass("hide");

    // 선택한 탭과 해당 콘텐츠 보이기
    $(tabElement).addClass("on");
    $("#" + tabId).removeClass("hide").addClass("show");

    // slick 슬라이더 초기화 및 첫 번째 슬라이드로 이동
    var $slider = $("#" + tabId).find(".tab-slide");
    if ($slider.length) {
        $slider.slick("setPosition");
        $slider.slick("slickGoTo", 0);
    }
}
