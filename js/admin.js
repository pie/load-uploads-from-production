jQuery(document).ready(function ($) {
  let noticeCount = 0
  $('#clear-cache-button').on('click', function (e) {
    clearCacheButtonParent = $(this).parent()
    $.ajax({
      url: ajax_obj.ajax_url,
      type: 'get',
      data: {
        action: 'lufp_clear_image_cache',
      },
    })
      .done(function (response) {
        return response.data
      })
      .then(function (data) {
        data = data.data
        if (data.success) {
          add_notice(
            clearCacheButtonParent,
            'Your image cache has been cleared',
            'success',
          )
        }
        if (!data.success) {
          add_notice(
            clearCacheButtonParent,
            `Your image cache has <em>not</em> been cleared: ${data.message}`,
            'warning',
          )
        }
      })
  })

  function add_notice(target, message = '', type = 'success') {
    console.log(`Notice Count: ${noticeCount}`)
    target.prepend(
      `<div id="lufp-notice-${noticeCount}" class="notice notice-${type} is-dismissible lufp-notice"><p>${message}</p></div>`,
    )

    setTimeout(
      function (n) {
        $(`#lufp-notice-${n}`).fadeOut()
      },
      5000,
      [noticeCount++],
    )
  }
})
