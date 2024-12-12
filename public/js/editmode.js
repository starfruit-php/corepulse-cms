var divElement;
var placeholderDefault = 'Enter Here...';
var editableConfig = [];
var editableHtmlEls = {};

// Helper function to check duplicate editable elements
document.querySelectorAll('.pimcore_editable').forEach(editableEl => {
  if (editableHtmlEls[editableEl.id] && editableEl.dataset.name) {
    const message = "Duplicate editable name: " + editableEl.dataset.name;
    window.parent.postMessage(
      { id: 'error', action: 'error', data: message },
      'http://localhost:3002'
    );
    throw message;
  }
  editableHtmlEls[editableEl.id] = true;
});

// Click event listener
document.addEventListener('click', function (event) {
  event.preventDefault;
  let targetId = event.target.id;
  let data = event.target.getAttribute('data-corepulse');

  if (!targetId) {
    const closestElementWithId = event.target.closest('[id]');
    if (closestElementWithId) {
      targetId = closestElementWithId.id;
      data = closestElementWithId.getAttribute('data-corepulse');
    }
  }

  if (targetId) {
    window.parent.postMessage(
      { id: targetId, action: 'openModal', data: JSON.parse(data)},
      'http://localhost:3002'
    );
  }
});

// Listener for update messages
window.addEventListener('message', (event) => {
  const data = event.data;
  if (data.action === 'update') {
    updateIframeContent(data.id, data.value);
  }
});

// Function to update content inside the iframe
function updateIframeContent(id, value) {
  const updateElement = document.getElementById(id);
  
  if (updateElement) {
    const keys = Object.keys(value);
    
    if (keys.length > 0) {
      const keyName =  keys[0];
      const valueUpdate = value[keyName];
      
      //update data
      let dataOld = updateElement.getAttribute('data-corepulse');
      if (dataOld) dataOld = JSON.parse(dataOld);
      dataOld.convertData = valueUpdate;
      updateElement.setAttribute('data-corepulse', JSON.stringify(dataOld));

      //render data
      const type = updateElement.getAttribute('data-type');
      switch (type) {
        case 'block':
          renderBlock(updateElement, valueUpdate, [], keyName);
          break;
        case 'select':
        case 'multiselect':
          renderSelectPreview(updateElement, valueUpdate, [], keyName, type === 'multiselect');
          break;
        default:
          renderItem(updateElement, type, valueUpdate);
      }  
    }
  }
}

// Render content based on type
function renderItem(updateElement, type, value) {
  const element = renderType(type, value);
  updateElement.innerHTML = '';
  updateElement.appendChild(element);
}

// Render select or multiselect
function renderSelectPreview(rootElement, data, config = [], name = null, multi = false) {
  rootElement.innerHTML = "";
  config = name ? editableConfig[name] : config;

  if (multi && Array.isArray(data)) {
    const result = data.map(item => {
      const matchingConfig = config?.find(([key]) => key === item);
      return matchingConfig ? matchingConfig[1] : null;
    }).filter(Boolean); // Filter out null values

    rootElement.innerHTML = result.join(", ");
  } else if (!multi && typeof data === "string") {
    const matchingConfig = config?.find(([key]) => key === data);
    rootElement.innerHTML = matchingConfig ? matchingConfig[1] : "";
  }
}

// Render block type
function renderBlock(rootElement, data, config = [], name = null) {
  rootElement.innerHTML = "";
  config = name ? editableConfig[name] : config;

  if (!Array.isArray(data) || !Array.isArray(config)) return;

  data.forEach(item => {
    const itemDiv = document.createElement("div");
    itemDiv.className = "corepulse-block-item";

    config.forEach(configItem => {
      const mapItemData = item?.[configItem.realName];
      const tag = renderType(configItem.type, mapItemData);
      tag.classList.add(configItem.type === "image" ? "corepulse-block-item-image" : "corepulse-block-item-default");

      itemDiv.appendChild(tag);
    });

    rootElement.appendChild(itemDiv);
  });

  rootElement.classList.add("corepulse-block-list");
}

// Render types of elements
function renderType(type, value) {
  const element = document.createElement(type === 'image' ? 'img' : 'div');

  switch (type) {
    case "image":
      if (value?.[0]) {
        element.height = "150";
        element.src = value[0]["fullPath"];
        element.dataset.id = value[0]["id"];
      }
      break;
    case "checkbox":
      var checkbox = document.createElement("input");
      checkbox.type = "checkbox";
      checkbox.disabled = true;
      checkbox.checked = value;

      element.appendChild(checkbox);
      break;
    case "link":
      const text = document.createElement("p");
      text.innerHTML = `<b>Text:</b> ${value?.text}`;
      element.appendChild(text);

      const path = document.createElement("p");
      path.innerHTML = `<b>Path:</b> ${value?.path}`;
      element.appendChild(path);
      element.className = 'corepulse-link-item';
      break;
    case "video":
      const video = document.createElement("video");
      if (value?.path) {
        video.height = "150";
        video.width =  "200";
        const source = document.createElement("source");
        source.src = value.path;
        source.type = "video/mp4";
        video.appendChild(source);
        video.controls = true;
      }
      return video;
    case "wysiwyg":
      element.innerHTML = value;
      break;
    case "relation":
      element.innerHTML = value?.key;
      break;
    case "relations":
      const convert = value?.map(item => item?.key).join(', ');
      element.innerHTML = convert;
      break;
    default:
      element.textContent = `${value}`;
  }

  return element;
}

// Initialize editable content
window.parent.postMessage(
  { id: 'initConfig', action: 'initConfig', data: {config: editableDefinitions, dataDocument: dataDocument} },
  'http://localhost:3002'
);

// Render editable content
editableDefinitions.forEach(element => {
  if (element.name in dataDocument) {
    element.convertData = dataDocument[element.name];
  }

  divElement = document.getElementById(element.id);
  divElement.innerHTML = '';
  divElement.classList.add('editable-paragraph');
  divElement.setAttribute('contenteditable', 'false');
  divElement.setAttribute('placeholder', element.config.placeholder ?? placeholderDefault);
  divElement.setAttribute('data-corepulse', JSON.stringify(element));

  switch (element.type) {
    case 'block':
      const data = element.convertData || [];
      const config = element.config.template?.editables || [];
      editableConfig[element.name] = config;
      renderBlock(divElement, data, config);
      break;
    case 'select':
    case 'multiselect':
      const selectConfig = element.config.store || [];
      editableConfig[element.name] = selectConfig;
      renderSelectPreview(divElement, element.convertData, selectConfig, element.name, element.type === 'multiselect');
      break;
    default:
      const elementTag = renderType(element.type, element.convertData);
      divElement.appendChild(elementTag);
  }
});
