var divElement;
var placeholderDefault = 'Enter Here...';

editableDefinitions.forEach(function (element) {

  // Truy cập đến phần tử div có name='abc'
  divElement = document.getElementById(element.id);

  if (element.type == 'input') {
    // Thêm lớp cho div
    if ( divElement) {
      divElement.classList.add('editable-paragraph');

      // Thiết lập thuộc tính contenteditable thành true
      divElement.setAttribute('contenteditable', 'true');
      divElement.setAttribute('placeholder', element.config.placeholder ?? placeholderDefault);
    }

    // Thêm nội dung mặc định cho div
    if (element.name in dataDocument) {
      divElement.innerHTML = dataDocument[element.name];
    }
  }

  if (element.type == 'checkbox') {
    var checkboxElement = document.createElement('input');
    checkboxElement.type = 'checkbox';

    // Kiểm tra nếu giá trị trong dataDocument là true, thì đánh dấu checkbox
    if (element.name in dataDocument) {
      checkboxElement.checked = dataDocument[element.name];
    }

    // Thêm checkbox vào div
    divElement.appendChild(checkboxElement);
  }

  if (element.type == 'numeric') {
    // Thêm lớp cho div
    divElement.classList.add('editable-paragraph');

    // Thiết lập thuộc tính contenteditable thành true
    divElement.setAttribute('contenteditable', 'true');
    divElement.setAttribute('placeholder', element.config.placeholder ?? placeholderDefault);

    // Thêm nội dung mặc định cho div
    if (element.name in dataDocument) {
      divElement.innerHTML = dataDocument[element.name];
    }
  }

  if (element.type == 'date') {
    // Thêm lớp cho div
    divElement.classList.add('editable-paragraph');

    // Thiết lập thuộc tính contenteditable thành true
    divElement.setAttribute('contenteditable', 'true');
    divElement.setAttribute('placeholder', element.config.placeholder ?? placeholderDefault);

    // Thêm nội dung mặc định cho div
    if (element.name in dataDocument) {
      divElement.innerHTML = dataDocument[element.name];
    }
  }

  if (element.type == 'select') {
    // Thêm lớp cho div
    divElement.classList.add('editable-paragraph');

    // Thiết lập thuộc tính contenteditable thành true
    divElement.setAttribute('contenteditable', 'true');
    divElement.setAttribute('placeholder', element.config.placeholder ?? placeholderDefault);

    // Thêm nội dung mặc định cho div
    if (element.name in dataDocument) {
      divElement.innerHTML = dataDocument[element.name];
    }

  }

  if (element.type == 'multiselect') {
    // Thêm lớp cho div
    divElement.classList.add('editable-paragraph');

    // Thiết lập thuộc tính contenteditable thành true
    divElement.setAttribute('contenteditable', 'true');
    divElement.setAttribute('placeholder', element.config.placeholder ?? placeholderDefault);

    // Thêm nội dung mặc định cho div
    if (element.name in dataDocument) {
      divElement.innerHTML = dataDocument[element.name];
    }
  }

  if (element.type == 'textarea') {
    // Thêm lớp cho div
    divElement.classList.add('editable-paragraph');

    // Thiết lập thuộc tính contenteditable thành true
    divElement.setAttribute('contenteditable', 'true');
    divElement.setAttribute('placeholder', element.config.placeholder ?? placeholderDefault);

    if (element.name in dataDocument) {
      divElement.innerHTML = dataDocument[element.name];
    }
  }

  if (element.type == 'wysiwyg') {
    // Thêm lớp cho div
    divElement.classList.add('editable-paragraph');

    // Thiết lập thuộc tính contenteditable thành true
    divElement.setAttribute('contenteditable', 'true');
    divElement.setAttribute('placeholder', element.config.placeholder ?? placeholderDefault);

    // Thêm nội dung mặc định cho div
    if (element.name in dataDocument) {
      divElement.innerHTML = dataDocument[element.name];
    }

    if (element.config.height) {
      divElement.style.height = element.config.height;
    }
    if (element.config.width) {
      divElement.style.width = element.config.width;
    }
  }

  if (element.type == 'video') {
    // Tạo phần tử video
    var videoElement = document.createElement('video');

    // Đặt thuộc tính src để chỉ định nguồn video
    videoElement.src = '';

    // Đặt thuộc tính controls để hiển thị thanh điều khiển video
    videoElement.controls = true;

  }

  if (element.type == 'relation') {
    // Thêm lớp cho div
    divElement.classList.add('editable-paragraph');

    divElement.setAttribute('contenteditable', 'true');
    divElement.setAttribute('placeholder', element.config.placeholder ?? placeholderDefault);

    // Thêm nội dung mặc định cho div
    if (element.name in dataDocument) {
      // console.log(element.name);
      divElement.innerHTML = dataDocument[element.name]['name']
      divElement.dataset.id = dataDocument[element.name]['id'];
      divElement.dataset.type = dataDocument[element.name]['type'];
      divElement.dataset.subtype = dataDocument[element.name]['subtype'];
    }

    var listingDoc = [];
    if (dataDocument['listingDoc']) {
      listingDoc = dataDocument['listingDoc']
    }
  }

  if (element.type == 'link') {
    // Thêm lớp cho div
    divElement.classList.add('editable-paragraph');

    divElement.setAttribute('contenteditable', 'true');
    divElement.setAttribute('placeholder', element.config.placeholder ?? placeholderDefault);

    if (element.name in dataDocument) {
      if (divElement.dataset.hasOwnProperty('text')) {
        divElement.dataset.text = dataDocument[element.name]['text'] ? dataDocument[element.name]['text'] : '';
      }
      if (dataDocument[element.name]) {
        divElement.dataset.path = dataDocument[element.name]['path'];
        divElement.dataset.target = dataDocument[element.name]['target'];
        divElement.dataset.parameters = dataDocument[element.name]['parameters'];
        divElement.dataset.anchor = dataDocument[element.name]['anchor'];
        divElement.dataset.title = dataDocument[element.name]['title'] ? dataDocument[element.name]['title'] : '';
        divElement.dataset.accesskey = dataDocument[element.name]['accesskey'];
        divElement.dataset.rel = dataDocument[element.name]['rel'];
        divElement.dataset.tabindex = dataDocument[element.name]['tabindex'];
        divElement.dataset.class = dataDocument[element.name]['class'];
    
        divElement.innerHTML = dataDocument[element.name]['path'];
      }
    }
  }

  if (element.type == 'table') {
    function addRow() {
      var table = document.querySelector('.table-row');
      var newRow = table.insertRow(table.rows.length); // Thêm hàng mới vào cuối bảng
      var numColumns = table.rows[0].cells.length; // Số cột trong bảng
    
      for (var i = 0; i < numColumns; i++) {
        var cell = newRow.insertCell(i);
      }
    }
    
    function addColumn() {
      var table = document.querySelector('.table-row');
      var numRows = table.rows.length;
    
      for (var i = 0; i < numRows; i++) {
        var cell = table.rows[i].insertCell(table.rows[i].cells.length);
      }
    }

    function deleteRow() {
      var table = document.querySelector('.table-row');
      var lastRowIndex = table.rows.length - 1;
      table.deleteRow(lastRowIndex);
    }
    
    function deleteColumn() {
      var table = document.querySelector('.table-row');
    
      for (var i = 0; i < table.rows.length; i++) {
          var lastCellIndex = table.rows[i].cells.length - 1;
          if (lastCellIndex >= 0) {
              table.rows[i].deleteCell(lastCellIndex);
          }
      }
    }    

    function clearTable() {
      var table = document.querySelector('.table-row');
  
      // Remove all rows
      while (table.rows.length > 0) {
          table.deleteRow(0);
      }
  
      // Create a new row and add an empty cell to it
      var newRow = table.insertRow(0);
      newRow.insertCell(0);
    }

    // Thêm lớp cho div
    divElement.classList.add('editable-paragraph');

    // Tạo nút "Thêm cột"
    var addColumnButton = document.createElement('button');
    addColumnButton.textContent = 'Add column';
    addColumnButton.onclick = addColumn;
    addColumnButton.classList.add('custom-button-class');
    addColumnButton.innerHTML = '<i class="fas fa-plus"></i>' + addColumnButton.innerHTML;

    // Tạo nút "Thêm hàng"
    var addRowButton = document.createElement('button');
    addRowButton.textContent = 'Add row';
    addRowButton.onclick = addRow;
    addRowButton.classList.add('custom-button-class');
    addRowButton.innerHTML = '<i class="fas fa-plus"></i>' + addRowButton.innerHTML;

    // Tạo nút "Xóa cột"
    var deleteColumnButton = document.createElement('button');
    deleteColumnButton.textContent = 'Delete column';
    deleteColumnButton.onclick = deleteColumn;
    deleteColumnButton.classList.add('custom-button-class');
    deleteColumnButton.innerHTML = '<i class="fas fa-trash"></i>' + deleteColumnButton.innerHTML;

    // Tạo nút "Xóa hàng"
    var deleteRowButton = document.createElement('button');
    deleteRowButton.textContent = 'Delete row';
    deleteRowButton.onclick = deleteRow;
    deleteRowButton.classList.add('custom-button-class');
    deleteRowButton.innerHTML = '<i class="fas fa-trash"></i>' + deleteRowButton.innerHTML;

    // Tạo nút "Clear"
    var clearButton = document.createElement('button');
    clearButton.textContent = 'Clear';
    clearButton.onclick = clearTable;
    clearButton.classList.add('custom-button-class');
    clearButton.innerHTML = '<i class="fas fa-times"></i>' + clearButton.innerHTML;

    // Thêm nút vào div
    divElement.appendChild(addColumnButton);
    divElement.appendChild(addRowButton);
    divElement.appendChild(deleteColumnButton);
    divElement.appendChild(deleteRowButton);
    divElement.appendChild(clearButton);

    var dataTable = dataDocument[element.name];

    let table = document.createElement('table');
    table.setAttribute('contenteditable', 'true');

    table.classList.add('table-row');

    if (dataTable != '') {
      for (let i = 0; i < dataTable.length; i++) {
        let row = document.createElement('tr');
  
        for (let j = 0; j < dataTable[i].length; j++) {
          let cell = document.createElement('td');
          cell.textContent = dataTable[i][j];
          row.appendChild(cell);
        }
  
        table.appendChild(row);
      }
    } else {
      if (element.config.defaults.data) {
        var defaultData = element.config.defaults.data;
        for (var j = 0; j <= defaultData.length - 1; j++) {
          let row = document.createElement('tr');
    
          for (var k = 0; k < defaultData[0].length; k++) {
            let cell = document.createElement('td');
            cell.innerHTML = defaultData[j][k];
            row.appendChild(cell);
          }
          table.appendChild(row);
        }
      } else {
        let row = document.createElement('tr');
        let cell = document.createElement('td');
        row.appendChild(cell);
        table.appendChild(row);
      }
    }

    divElement.appendChild(table);
  }

  if (element.type == 'renderlet') {
    // Thêm lớp cho div
    divElement.classList.add('editable-paragraph');

    divElement.setAttribute('contenteditable', 'true');
    divElement.setAttribute('placeholder', element.config.placeholder ?? placeholderDefault);

    divElement.dataset.id = dataDocument[element.name]['id'];
    divElement.dataset.type = dataDocument[element.name]['type'];
    divElement.dataset.subtype = dataDocument[element.name]['subtype'];

    var option = JSON.stringify(element.config);
    var name = element.name;

    const params = new URLSearchParams();
    params.set('id', pimcore_document_id);
    params.set('option', option);
    params.set('name', name);

    // Tạo URL của API với params
    const apiUrl = '../renderlet';
    const apiWithParamsUrl = `${apiUrl}?${params.toString()}`;

    if (element.name in dataDocument) {
      fetch(apiWithParamsUrl)
      .then(response => response.json())
      .then(data => {
        divElement.innerHTML = data.data
      })
      .catch(error => {
        console.log(error);
      });  
    }
  }

  if (element.type == 'image') {
    // Thêm lớp cho div
    divElement.classList.add('editable-paragraph');
    
    var image = document.createElement("img");

    if (element.name in dataDocument) {
      var image = document.createElement("img");

      image.src = dataDocument[element.name]['thumbPath'];
      if (element.config.height) {
        image.height = element.config.height;
      }
      image.dataset.id = dataDocument[element.name]['id'];
      image.dataset.type = dataDocument[element.name]['type'];
      image.dataset.subtype = dataDocument[element.name]['subtype'];
      image.dataset.src = dataDocument[element.name]['linkImage'];
    }
    
    divElement.append(image);
  }

  if (element.type == 'pdf') {
    // Thêm lớp cho div
    divElement.classList.add('editable-paragraph');

    divElement.setAttribute('contenteditable', 'true');
    divElement.setAttribute('placeholder', element.config.placeholder ?? placeholderDefault);

    if (element.name in dataDocument) {
      divElement.innerHTML = dataDocument[element.name]['linkPDF'];
      divElement.dataset.id = dataDocument[element.name]['id'];
    }
  }

  if (element.type == 'video') {
    // Thêm lớp cho div
    divElement.classList.add('editable-paragraph');
    
    divElement.dataset.type = dataDocument[element.name]['type'];
    divElement.dataset.title = dataDocument[element.name]['title'];
    divElement.dataset.description = dataDocument[element.name]['description'];
    divElement.dataset.poster = dataDocument[element.name]['poster'];
    divElement.dataset.path = dataDocument[element.name]['path'];

    
    var video = document.createElement("video");
    if (element.name in dataDocument) {

      if (element.config.height) {
        video.height = element.config.height;
      }
      if (element.config.width) {
        video.width = element.config.width;
      }

      var source = document.createElement("source");
      source.src = dataDocument[element.name]['path'];
      source.type = "video/mp4";

      var videoEmpty = document.getElementsByClassName('pimcore_editable_video_empty');
      if (dataDocument[element.name]['path'] != '') {
        video.controls = true;
        video.poster = dataDocument[element.name]['poster'];
      } else {
        var image = document.createElement("img");

        image.src = '/bundles/pimcoreadmin/img/filetype-not-supported.svg';
        videoEmpty[0].append(image);
      }

      video.append(source);
    }
    
    // divElement.append(video);
  }

  if (element.type == 'relations') {
    divElement.classList.add('editable-paragraph');
    var relationsDefaut = '';
    if (element.name in dataDocument) {
      relationsDefaut += '<table class="tab-relations" border="1"><tr><th>Id</th><th>Name</th><th>Type</th><th>SubType</th></tr>';

      dataDocument[element.name].forEach(function (element) {
        // Thêm mỗi dòng vào bảng với dữ liệu tương ứng
        relationsDefaut += '<tr><td>' + element['id'] + '</td><td>' + element['name'] + '</td><td>' + element['type'] + '</td><td>' + element['subType'] + '</td></tr>';
      });
    
      // Đóng bảng
      relationsDefaut += '</table>';
      divElement.innerHTML = relationsDefaut;
    } else {
      divElement.innerHTML = 'The relation has not been created';
    }
  }

  if (element.type == 'block') {
    // Thêm lớp cho div
    divElement.classList.add('editable-paragraph');
  }

  if (element.type == 'snippet') {
    // Thêm lớp cho div
    divElement.classList.add('editable-paragraph');
    
    // Thêm nội dung mặc định cho div
    var relationsDefaut = '';
    if (element.name in dataDocument) {
      relationsDefaut = "Name Snippeted: " + dataDocument[element.name]['name']
    }
    divElement.innerHTML = relationsDefaut;
  }

  if (element.type == 'scheduledblock') {
    // Thêm lớp cho div
    divElement.classList.add('editable-paragraph');
    divElement.innerHTML = 'Scheduled In Here';
  }

});